<?php

namespace App\Models\Business;

use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function allows(string $permission): bool
    {
        $list = $this->permissions ?? [];

        return in_array('*', $list, true) || in_array($permission, $list, true);
    }

    /** Every permission key defined in config/permissions.php. */
    public static function catalogKeys(): array
    {
        $keys = [];
        foreach (config('permissions.modules', []) as $module => $def) {
            foreach (array_keys($def['actions']) as $action) {
                $keys[] = "{$module}.{$action}";
            }
        }

        return $keys;
    }

    /** Expanded permission list ("*" becomes every key). */
    public function expanded(): array
    {
        $list = $this->permissions ?? [];

        return in_array('*', $list, true) ? self::catalogKeys() : array_values(array_intersect($list, self::catalogKeys()));
    }
}
