<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Business step 2: CRM. Leads (pipeline), their activity timeline / follow-ups,
| and customers. Website "clients" (logo list) is a different, untouched table.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('name');
            $t->string('phone', 50)->nullable()->index();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('company')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('name');
            $t->string('phone', 50)->nullable()->index();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('source', 30)->default('website');
            $t->string('stage', 30)->default('new')->index();
            $t->string('service')->nullable();
            $t->string('property_type')->nullable();
            $t->string('area_size')->nullable();
            $t->string('budget')->nullable();
            $t->decimal('expected_value', 14, 2)->nullable();
            $t->text('requirements')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('inquiry_id')->nullable()->constrained('inquiries')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->timestamp('next_follow_up_at')->nullable()->index();
            $t->string('lost_reason')->nullable();
            $t->timestamp('won_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('lead_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 20); // call, meeting, visit, note, stage, follow_up
            $t->text('note')->nullable();
            $t->timestamp('follow_up_at')->nullable()->index();
            $t->timestamp('done_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('customers');
    }
};
