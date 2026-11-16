<?php

namespace App\Support;

use App\Models\Business\Account;
use App\Models\Business\Branch;
use App\Models\Business\Store;
use App\Models\Business\TransactionCategory;
use App\Models\Business\Role;
use App\Models\Business\TaxRate;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent setup of the business foundation data. Safe to run any number of times:
 * it only creates what is missing and never overwrites what the Admin has edited.
 *  - default roles (config/permissions.php)
 *  - a "Head Office" branch when no branch exists
 *  - users without a role become Admin and are attached to the first branch
 *  - a default VAT rate when no tax rate exists
 * Called from a migration (so cPanel /install picks it up) and from DatabaseSeeder.
 */
class BusinessFoundation
{
    public static function ensure(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('branches')) {
            return;
        }

        foreach (config('permissions.default_roles', []) as $slug => $def) {
            Role::firstOrCreate(['slug' => $slug], [
                'name' => $def['name'],
                'permissions' => $def['permissions'],
                'is_system' => $def['system'] ?? false,
            ]);
        }

        $branch = Branch::orderBy('id')->first() ?? Branch::create([
            'name' => 'Head Office',
            'code' => 'HO',
            'is_active' => true,
        ]);

        $admin = Role::where('slug', 'admin')->first();
        User::whereNull('role_id')->get()->each(function (User $u) use ($admin, $branch) {
            $u->forceFill(['role_id' => $admin->id, 'is_active' => true])->saveQuietly();
            $u->branches()->syncWithoutDetaching([$branch->id]);
        });

        if (TaxRate::count() === 0) {
            TaxRate::create(['name' => 'VAT', 'rate' => 15, 'is_default' => true, 'is_active' => true]);
        }

        self::ensureAccounts();
    }

    /** System income/expense categories (used by automatic postings) + a Cash account per branch. */
    public static function ensureAccounts(): void
    {
        if (! Schema::hasTable('transaction_categories') || ! Schema::hasTable('accounts')) {
            return;
        }

        $system = [
            ['income', 'Customer Payment', 'customer_payment'],
            ['income', 'Other Income', 'other_income'],
            ['expense', 'Supplier Payment', 'supplier_payment'],
            ['expense', 'Other Expense', 'other_expense'],
        ];
        foreach ($system as [$type, $name, $slug]) {
            TransactionCategory::firstOrCreate(['slug' => $slug], ['type' => $type, 'name' => $name, 'is_system' => true]);
        }
        if (TransactionCategory::where('is_system', false)->count() === 0) {
            foreach (['Material Purchase', 'Labour / Contractor', 'Transport', 'Office Rent', 'Salary', 'Utility Bills', 'Marketing', 'Office Expense'] as $name) {
                TransactionCategory::create(['type' => 'expense', 'name' => $name]);
            }
        }

        Branch::all()->each(fn (Branch $b) => self::ensureCashAccount($b));
        Branch::all()->each(fn (Branch $b) => self::ensureStore($b));
    }

    /** Every branch starts with a "Main Store". */
    public static function ensureStore(Branch $branch): void
    {
        if (Schema::hasTable('stores') && ! Store::withoutGlobalScopes()->where('branch_id', $branch->id)->exists()) {
            Store::withoutGlobalScopes()->create(['branch_id' => $branch->id, 'name' => 'Main Store']);
        }
    }

    public static function ensureCashAccount(Branch $branch): void
    {
        if (Schema::hasTable('accounts') && ! Account::withoutGlobalScopes()->where('branch_id', $branch->id)->exists()) {
            Account::withoutGlobalScopes()->create(['branch_id' => $branch->id, 'name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0]);
        }
    }
}
