<?php

namespace App\Models;

use App\Models\Business\Branch;
use App\Models\Business\Role;
use App\Models\Concerns\LogsActivity;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, LogsActivity, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'role_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class);
    }

    public function hasPermission(string $permission): bool
    {
        return (bool) $this->role?->allows($permission);
    }

    public function permissionList(): array
    {
        return $this->role?->expanded() ?? [];
    }

    /** Branches this user may work in (all active branches with "branches.all"). */
    public function allowedBranches()
    {
        $q = Branch::query()->where('is_active', true)->orderBy('id');

        return $this->hasPermission('branches.all')
            ? $q->get(['id', 'name', 'code'])
            : $q->whereIn('id', $this->branches()->pluck('branches.id'))->get(['id', 'name', 'code']);
    }
}
