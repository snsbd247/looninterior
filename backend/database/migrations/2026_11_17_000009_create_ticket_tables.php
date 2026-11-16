<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Business step 7: service / warranty tickets and their comment timeline. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('work_project_id')->nullable()->constrained('work_projects')->nullOnDelete();
            $t->string('contact_name')->nullable();   // from the website form when no customer matched
            $t->string('contact_phone', 50)->nullable();
            $t->string('contact_email')->nullable();
            $t->text('address')->nullable();
            $t->string('subject');
            $t->text('description')->nullable();
            $t->string('priority', 10)->default('medium'); // low | medium | high | urgent
            $t->string('status', 15)->default('open')->index(); // open | in_progress | resolved | closed
            $t->string('source', 15)->default('phone'); // phone | website | email | walk_in | visit
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('visit_at')->nullable();
            $t->date('warranty_until')->nullable();   // snapshot from the project when the ticket was opened
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('ticket_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->string('attachment')->nullable(); // public uploads image
            $t->string('kind', 15)->default('comment'); // comment | status | system
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
    }
};
