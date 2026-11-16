<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /** Permission catalog grouped by module (drives the checkbox matrix). */
    public function catalog()
    {
        $modules = [];
        foreach (config('permissions.modules') as $key => $def) {
            $modules[] = [
                'key' => $key,
                'label' => $def['label'],
                'actions' => collect($def['actions'])->map(fn ($label, $action) => [
                    'key' => "{$key}.{$action}", 'action' => $action, 'label' => $label,
                ])->values(),
            ];
        }

        return response()->json(['data' => $modules]);
    }

    public function index()
    {
        $roles = Role::withCount('users')->orderBy('id')->get()
            ->map(fn (Role $r) => array_merge($r->toArray(), ['permissions' => $r->expanded(), 'all' => in_array('*', $r->permissions ?? [], true)]));

        return response()->json(['data' => $roles]);
    }

    public function show(Role $role)
    {
        return response()->json(['data' => array_merge($role->loadCount('users')->toArray(), [
            'permissions' => $role->expanded(),
            'all' => in_array('*', $role->permissions ?? [], true),
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $role = Role::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'permissions' => $data['permissions'],
        ]);

        return response()->json(['message' => 'Role created', 'data' => $role], 201);
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->is_system, 422, 'The Admin role always has every permission and cannot be changed.');
        $data = $this->validated($request, $role);
        $role->update(['name' => $data['name'], 'permissions' => $data['permissions']]);

        return response()->json(['message' => 'Role updated', 'data' => $role]);
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 422, 'The Admin role cannot be deleted.');
        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'Move the users of this role to another role first.']);
        }
        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }

    private function validated(Request $request, ?Role $role): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => 'present|array',
            'permissions.*' => ['string', Rule::in(Role::catalogKeys())],
        ]);
        $data['permissions'] = array_values(array_unique($data['permissions']));

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 2;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
