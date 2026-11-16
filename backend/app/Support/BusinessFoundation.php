<?php

namespace App\Support;

use App\Models\Business\Branch;
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
    }
}
