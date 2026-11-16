<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Business steps 4 + 5: running projects (work_projects, not the website portfolio "projects"),
| invoices, payments, credit notes, and the simple accounts (cash/bank/mobile + income/expense/transfer).
| Money rows are never deleted: they are cancelled (status) so balances and the audit trail stay correct.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('code', 50)->unique();
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $t->string('title');
            $t->text('site_address')->nullable();
            $t->decimal('contract_value', 14, 2)->default(0);
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->date('handover_date')->nullable();
            $t->date('warranty_until')->nullable();
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('planning')->index();
            $t->unsignedTinyInteger('progress')->default(0);
            $t->text('description')->nullable();
            $t->foreignId('portfolio_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('project_milestones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_project_id')->constrained('work_projects')->cascadeOnDelete();
            $t->string('name');
            $t->unsignedTinyInteger('progress')->default(0);
            $t->date('due_date')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_project_id')->constrained('work_projects')->cascadeOnDelete();
            $t->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
            $t->string('title');
            $t->text('notes')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->date('due_date')->nullable();
            $t->string('status', 20)->default('todo');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('project_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_project_id')->constrained('work_projects')->cascadeOnDelete();
            $t->string('kind', 10); // image (public uploads) | doc (private storage)
            $t->string('path');
            $t->string('original_name');
            $t->unsignedInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('work_project_id')->nullable()->constrained('work_projects')->nullOnDelete();
            $t->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $t->string('title')->nullable();
            $t->date('date');
            $t->date('due_date')->nullable();
            $t->string('status', 20)->default('draft')->index();
            $t->string('discount_type', 10)->default('amount');
            $t->decimal('discount_value', 14, 2)->default(0);
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('discount', 14, 2)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $t->string('tax_name', 100)->nullable();
            $t->decimal('tax_rate', 6, 3)->default(0);
            $t->decimal('tax', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->decimal('paid', 14, 2)->default(0);
            $t->decimal('credited', 14, 2)->default(0);
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $t->string('section')->nullable();
            $t->text('description');
            $t->string('unit', 30)->nullable();
            $t->decimal('qty', 12, 3)->default(1);
            $t->decimal('rate', 14, 2)->default(0);
            $t->decimal('amount', 14, 2)->default(0);
            $t->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('payment_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_project_id')->constrained('work_projects')->cascadeOnDelete();
            $t->string('label');
            $t->decimal('percent', 6, 2)->nullable();
            $t->decimal('amount', 14, 2)->default(0);
            $t->date('due_date')->nullable();
            $t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('name');
            $t->string('type', 10); // cash | bank | mobile
            $t->string('bank_name')->nullable();
            $t->string('account_number', 100)->nullable();
            $t->decimal('opening_balance', 14, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('transaction_categories', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10); // income | expense
            $t->string('name');
            $t->string('slug', 50)->nullable()->unique(); // system categories used by automatic postings
            $t->boolean('is_system')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->date('date')->index();
            $t->string('type', 15); // income | expense | transfer_in | transfer_out
            $t->foreignId('account_id')->constrained('accounts');
            $t->foreignId('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $t->decimal('amount', 14, 2);
            $t->foreignId('work_project_id')->nullable()->constrained('work_projects')->nullOnDelete();
            $t->nullableMorphs('party');   // customer / supplier
            $t->nullableMorphs('source');  // payment / supplier payment / …
            $t->string('description', 500)->nullable();
            $t->string('reference', 100)->nullable();
            $t->string('attachment')->nullable();
            $t->string('transfer_group', 40)->nullable()->index();
            $t->string('status', 15)->default('active')->index();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['account_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->foreignId('invoice_id')->constrained('invoices');
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('account_id')->constrained('accounts');
            $t->string('method', 20); // cash | bank | bkash | nagad | cheque | other
            $t->decimal('amount', 14, 2);
            $t->string('reference', 100)->nullable();
            $t->date('date');
            $t->text('notes')->nullable();
            $t->string('status', 15)->default('received')->index();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->foreignId('invoice_id')->constrained('invoices');
            $t->decimal('amount', 14, 2);
            $t->string('reason');
            $t->date('date');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['credit_notes', 'payments', 'transactions', 'transaction_categories', 'accounts', 'payment_schedules',
            'invoice_items', 'invoices', 'project_files', 'project_tasks', 'project_milestones', 'work_projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
