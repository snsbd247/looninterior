<?php

use App\Models\Business\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Phase F: HR & payroll, labour & contractors, client portal login, and a "Salary" /
| "Labour / Contractor" system category. Additive only: customers gets new nullable columns.
| The default roles receive the new permissions only if the Admin has not removed the role.
*/
return new class extends Migration
{
    public function up(): void
    {
        // ---------------- HR & payroll ----------------
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('code', 30)->nullable();
            $t->string('name');
            $t->string('designation')->nullable();
            $t->string('department')->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('nid', 50)->nullable();
            $t->date('join_date')->nullable();
            $t->decimal('basic_salary', 14, 2)->default(0);
            $t->decimal('allowance', 14, 2)->default(0);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('date');
            $t->string('status', 12); // present | absent | half_day | leave | holiday
            $t->string('note')->nullable();
            $t->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['employee_id', 'date']);
        });

        Schema::create('leaves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->string('type', 12); // casual | sick | annual | unpaid
            $t->date('from_date');
            $t->date('to_date');
            $t->decimal('days', 5, 1);
            $t->string('reason')->nullable();
            $t->string('status', 12)->default('pending'); // pending | approved | rejected
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->string('month', 7); // YYYY-MM
            $t->unsignedTinyInteger('working_days');
            $t->string('status', 12)->default('draft'); // draft | finalized | paid | cancelled
            $t->decimal('total', 14, 2)->default(0);
            $t->foreignId('account_id')->nullable()->constrained('accounts');
            $t->date('paid_date')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['branch_id', 'month']);
        });

        Schema::create('payroll_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees');
            $t->decimal('basic', 14, 2);
            $t->decimal('allowance', 14, 2)->default(0);
            $t->decimal('present_days', 5, 1)->default(0);
            $t->decimal('absent_days', 5, 1)->default(0);
            $t->decimal('unpaid_leave_days', 5, 1)->default(0);
            $t->decimal('deduction', 14, 2)->default(0);   // absence + unpaid leave (calculated)
            $t->decimal('bonus', 14, 2)->default(0);
            $t->decimal('advance', 14, 2)->default(0);     // salary advance recovered
            $t->decimal('net', 14, 2)->default(0);
            $t->string('note')->nullable();
        });

        // ---------------- Labour & contractors ----------------
        Schema::create('contractors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('trade', 30)->nullable(); // carpenter, painter, electrician, mason, plumber, labour, other
            $t->string('phone', 50)->nullable();
            $t->decimal('daily_rate', 14, 2)->default(0);
            $t->decimal('opening_due', 14, 2)->default(0);
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('labour_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->foreignId('work_project_id')->constrained('work_projects');
            $t->foreignId('contractor_id')->nullable()->constrained('contractors');
            $t->date('date');
            $t->string('description');
            $t->unsignedSmallInteger('workers')->default(1);
            $t->decimal('days', 6, 2)->default(1);
            $t->decimal('rate', 14, 2)->default(0);   // per worker per day, or the job price when workers = days = 1
            $t->decimal('amount', 14, 2);
            $t->string('status', 12)->default('active'); // active | cancelled
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('contractor_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('number', 50)->unique();
            $t->foreignId('contractor_id')->constrained('contractors');
            $t->foreignId('account_id')->constrained('accounts');
            $t->string('method', 20);
            $t->decimal('amount', 14, 2);
            $t->string('reference', 100)->nullable();
            $t->date('date');
            $t->string('notes')->nullable();
            $t->string('status', 12)->default('paid'); // paid | cancelled
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // ---------------- Client portal ----------------
        Schema::table('customers', function (Blueprint $t) {
            $t->boolean('portal_enabled')->default(false)->after('notes');
            $t->string('portal_password')->nullable()->after('portal_enabled');
            $t->timestamp('portal_last_login')->nullable()->after('portal_password');
        });

        // ---------------- System categories ----------------
        foreach ([['expense', 'Salary', 'salary'], ['expense', 'Labour / Contractor', 'labour']] as [$type, $name, $slug]) {
            $existing = DB::table('transaction_categories')->where('name', $name)->where('type', $type)->first();
            if ($existing) {
                DB::table('transaction_categories')->where('id', $existing->id)->update(['slug' => $slug, 'is_system' => true]);
            } elseif (! DB::table('transaction_categories')->where('slug', $slug)->exists()) {
                DB::table('transaction_categories')->insert(['type' => $type, 'name' => $name, 'slug' => $slug, 'is_system' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // New permissions for the existing default roles (additive)
        $add = ['accounts' => ['hr.view', 'hr.pay', 'labour.view', 'labour.pay'], 'sales' => ['labour.view', 'labour.manage', 'portal.manage']];
        foreach ($add as $slug => $perms) {
            $role = Role::where('slug', $slug)->first();
            if ($role) {
                $role->permissions = array_values(array_unique(array_merge($role->permissions ?? [], $perms)));
                $role->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['portal_enabled', 'portal_password', 'portal_last_login']));
        foreach (['contractor_payments', 'labour_entries', 'contractors', 'payroll_items', 'payrolls', 'leaves', 'attendances', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
