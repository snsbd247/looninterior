<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Business step 6: inventory & purchase.
| Items, categories and suppliers are company-wide; stores, purchase docs, stock entries and
| supplier payments belong to a branch. Stock is a ledger (stock_movements); stock_balances is a
| per-store cache kept in the same transaction. Valuation: weighted average cost (items.avg_cost).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('name');
            $t->text('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('item_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 50)->nullable()->unique();
            $t->string('name');
            $t->foreignId('item_category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $t->string('unit', 30)->default('pcs');
            $t->decimal('min_stock', 14, 3)->default(0);
            $t->decimal('avg_cost', 14, 4)->default(0);
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 50)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->decimal('opening_due', 14, 2)->default(0);
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->foreignId('supplier_id')->constrained('suppliers');
            $t->foreignId('store_id')->constrained('stores');
            $t->date('date');
            $t->date('expected_date')->nullable();
            $t->string('status', 20)->default('draft')->index(); // draft, sent, partial, received, cancelled
            $t->decimal('total', 14, 2)->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $t->foreignId('item_id')->constrained('items');
            $t->string('description')->nullable();
            $t->decimal('qty', 14, 3);
            $t->decimal('rate', 14, 2);
            $t->decimal('amount', 14, 2);
            $t->decimal('received_qty', 14, 3)->default(0);
            $t->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->string('type', 10); // grn (into a store) | direct (straight to a project)
            $t->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $t->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $t->foreignId('store_id')->nullable()->constrained('stores');
            $t->foreignId('work_project_id')->nullable()->constrained('work_projects');
            $t->string('supplier_ref', 100)->nullable(); // supplier bill / challan no.
            $t->date('date');
            $t->decimal('total', 14, 2)->default(0);
            $t->decimal('paid', 14, 2)->default(0);
            $t->string('status', 15)->default('received')->index(); // received | cancelled
            $t->text('notes')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $t->foreignId('item_id')->nullable()->constrained('items');
            $t->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $t->string('description');
            $t->string('unit', 30)->nullable();
            $t->decimal('qty', 14, 3);
            $t->decimal('rate', 14, 2);
            $t->decimal('amount', 14, 2);
        });

        Schema::create('stock_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->string('type', 15); // issue | return | transfer | adjust
            $t->foreignId('store_id')->constrained('stores');
            $t->foreignId('to_store_id')->nullable()->constrained('stores');
            $t->foreignId('work_project_id')->nullable()->constrained('work_projects');
            $t->date('date');
            $t->text('notes')->nullable();
            $t->string('status', 15)->default('active'); // active | cancelled
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('stock_entry_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_entry_id')->constrained('stock_entries')->cascadeOnDelete();
            $t->foreignId('item_id')->constrained('items');
            $t->decimal('qty', 14, 3); // signed for adjustments, positive otherwise
            $t->decimal('unit_cost', 14, 4)->default(0);
            $t->decimal('amount', 14, 2)->default(0);
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->foreignId('store_id')->constrained('stores');
            $t->foreignId('item_id')->constrained('items');
            $t->string('type', 15); // in, issue, return, transfer_in, transfer_out, adjust, reverse
            $t->decimal('qty', 14, 3);        // + into the store, − out of the store
            $t->decimal('unit_cost', 14, 4);
            $t->decimal('value', 14, 2);      // qty × unit_cost (signed)
            $t->foreignId('work_project_id')->nullable()->constrained('work_projects');
            $t->nullableMorphs('source');     // Purchase / StockEntry
            $t->date('date')->index();
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['item_id', 'store_id']);
        });

        Schema::create('stock_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained('stores');
            $t->foreignId('item_id')->constrained('items');
            $t->decimal('qty', 14, 3)->default(0);
            $t->timestamps();
            $t->unique(['store_id', 'item_id']);
        });

        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->foreignId('supplier_id')->nullable()->constrained('suppliers'); // null = cash purchase from market
            $t->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $t->foreignId('account_id')->constrained('accounts');
            $t->string('method', 20);
            $t->decimal('amount', 14, 2);
            $t->string('reference', 100)->nullable();
            $t->date('date');
            $t->text('notes')->nullable();
            $t->string('status', 15)->default('paid'); // paid | cancelled
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['supplier_payments', 'stock_balances', 'stock_movements', 'stock_entry_items', 'stock_entries', 'purchase_items',
            'purchases', 'purchase_order_items', 'purchase_orders', 'suppliers', 'items', 'item_categories', 'stores'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
