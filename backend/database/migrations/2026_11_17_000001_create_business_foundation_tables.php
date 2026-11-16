<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Business module, step 1 (foundation): branches, roles, user roles/branches,
| activity log, tax rates and document number sequences.
| Additive only: existing website tables are untouched (users only gets new nullable columns).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code', 10)->unique();
            $t->string('phone', 50)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug', 50)->unique();
            $t->json('permissions')->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
            $t->string('phone', 50)->nullable()->after('email');
            $t->boolean('is_active')->default(true)->after('role_id');
            $t->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::create('branch_user', function (Blueprint $t) {
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->primary(['branch_id', 'user_id']);
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->string('action', 30);
            $t->string('subject_type', 100)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('description', 500)->nullable();
            $t->json('changes')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable()->index();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('tax_rates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('rate', 6, 3)->default(0);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->string('type', 10);
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('last_number')->default(0);
            $t->timestamps();
            $t->unique(['branch_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('branch_user');
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('role_id');
            $t->dropColumn(['phone', 'is_active', 'last_login_at']);
        });
        Schema::dropIfExists('roles');
        Schema::dropIfExists('branches');
    }
};
