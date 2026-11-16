<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Business step 3: quotations / BOQ with room-wise sections and revisions.
| Revisions are separate rows sharing base_number (QT-0001, QT-0001-R1, …).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->string('base_number', 50)->index();
            $t->unsignedSmallInteger('revision')->default(0);
            $t->foreignId('parent_id')->nullable()->constrained('quotations')->nullOnDelete();
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $t->string('title')->nullable();
            $t->string('status', 20)->default('draft')->index();
            $t->date('date');
            $t->date('valid_until')->nullable();
            $t->string('discount_type', 10)->default('amount'); // amount | percent
            $t->decimal('discount_value', 14, 2)->default(0);
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('discount', 14, 2)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $t->string('tax_name', 100)->nullable();
            $t->decimal('tax_rate', 6, 3)->default(0);
            $t->decimal('tax', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $t->string('section')->nullable();
            $t->text('description');
            $t->string('unit', 30)->nullable();
            $t->decimal('qty', 12, 3)->default(1);
            $t->decimal('rate', 14, 2)->default(0);
            $t->decimal('amount', 14, 2)->default(0);
            $t->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
