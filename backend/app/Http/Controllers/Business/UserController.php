<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Role;
use App\Models\User;
use App\Services\Webmail;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff users. Users are never deleted (their history must stay in the logs
 * and documents): they are deactivated instead, which signs them out at once.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->with(['role:id,name,slug', 'branches:id,name,code'])->orderBy('name');

        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%"));
        }
        if ($request->filled('role_id')) {
            $q->where('role_id', $request->query('role_id'));
        }
        if ($request->filled('status')) {
            $q->where('is_active', $request->query('status') === 'active');
        }

        return response()->json($q->paginate(min(100, max(1, (int) $request->query('per_page', 20)))));
    }

    public function show(User $user)
    {
        return response()->json(['data' => $user->load(['role:id,name,slug', 'branches:id,name,code'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $created = $this->createMailbox($data);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'mailbox' => isset($data['mailbox']) ? strtolower($data['mailbox']) : null,
                'password' => $data['password'],
                'role_id' => $data['role_id'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $user->branches()->sync($data['branch_ids']);

            return $user;
        });

        return response()->json(['message' => 'User created'.$created, 'data' => $user->load(['role:id,name,slug', 'branches:id,name,code'])], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $self = $request->user()->is($user);

        // Protect against locking yourself out.
        if ($self && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
        }
        if ($self && (int) $data['role_id'] !== (int) $user->role_id) {
            throw ValidationException::withMessages(['role_id' => 'You cannot change your own role.']);
        }
        $this->guardLastAdmin($user, $data);
        $created = $this->createMailbox($data);
        $wasActive = (bool) $user->is_active;

        DB::transaction(function () use ($user, $data) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'mailbox' => isset($data['mailbox']) ? strtolower($data['mailbox']) : null,
                'role_id' => $data['role_id'],
                'is_active' => $data['is_active'] ?? $user->is_active,
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $passwordChanged = $user->isDirty('password');
            $user->save();

            $before = $user->branches()->pluck('branches.id')->sort()->values()->all();
            $user->branches()->sync($data['branch_ids']);
            $after = collect($data['branch_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($before !== $after) {
                ActivityLogger::record('updated', $user, 'Changed branches of user "'.$user->name.'"', ['before' => ['branch_ids' => $before], 'after' => ['branch_ids' => $after]]);
            }
            if ($passwordChanged) {
                ActivityLogger::record('password_changed', $user, 'Changed password of user "'.$user->name.'"');
            }
            if (! $user->is_active) {
                $user->tokens()->delete(); // signed out everywhere
            }
        });

        $note = $this->syncMailboxLogin($user->fresh(), $wasActive);

        return response()->json(['message' => 'User updated'.$created.$note, 'data' => $user->fresh()->load(['role:id,name,slug', 'branches:id,name,code'])]);
    }

    /**
     * "Create this mailbox": checked again on the server and created in cPanel before the user is saved,
     * so a cPanel error (weak password, quota…) stops the save with the reason.
     */
    private function createMailbox(array $data): string
    {
        if (empty($data['create_mailbox'])) {
            return '';
        }
        $box = Webmail::mailboxOf($data['mailbox'] ?? null, $data['email']);
        $field = ! empty($data['mailbox']) ? 'mailbox' : 'email';
        if (! $box || ! Webmail::onDomain($box)) {
            throw ValidationException::withMessages([$field => 'Only a company e-mail address can get a mailbox.']);
        }
        $check = Webmail::check($box);
        if ($check['status'] === 'exists') {
            return '';
        }
        if ($check['status'] !== 'available') {
            throw ValidationException::withMessages([$field => $check['message']]);
        }
        [$ok, $msg] = Webmail::create($box, $data['mailbox_password'] ?? null);
        if (! $ok) {
            throw ValidationException::withMessages([$field => 'cPanel could not create the mailbox: '.$msg]);
        }
        ActivityLogger::record('mailbox_created', null, "Created mailbox {$box}");

        return " · mailbox {$box} created";
    }

    /** Deactivating a user blocks sign-in to their mailbox (mail is kept); re-activating allows it again. */
    private function syncMailboxLogin(User $user, bool $wasActive): string
    {
        if ($wasActive === (bool) $user->is_active || ! Webmail::configured()) {
            return '';
        }
        $box = Webmail::mailboxFor($user);
        if (! $box || ! Webmail::onDomain($box) || Webmail::check($box)['status'] !== 'exists') {
            return '';
        }
        [$ok, $msg] = Webmail::setLogin($box, (bool) $user->is_active);
        ActivityLogger::record($user->is_active ? 'mailbox_login_allowed' : 'mailbox_login_blocked', $user,
            ($user->is_active ? 'Allowed' : 'Blocked')." mailbox sign-in {$box}".($ok ? '' : " (failed: {$msg})"));

        return $ok ? ($user->is_active ? ' · mailbox sign-in allowed' : ' · mailbox sign-in blocked') : " · mailbox sign-in could not be changed: {$msg}";
    }

    private function validated(Request $request, ?User $user): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => 'nullable|string|max:50',
            'mailbox' => 'nullable|email|max:255',
            'create_mailbox' => 'sometimes|boolean',
            'mailbox_password' => 'nullable|string|min:8|max:100',
            'role_id' => 'required|integer|exists:roles,id',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            'is_active' => 'sometimes|boolean',
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ], [
            'branch_ids.required' => 'Select at least one branch.',
        ]);
    }

    /** There must always be at least one active Admin. */
    private function guardLastAdmin(User $user, array $data): void
    {
        $admin = Role::where('slug', 'admin')->value('id');
        if ((int) $user->role_id !== (int) $admin) {
            return;
        }
        $losesAdmin = (int) $data['role_id'] !== (int) $admin || (array_key_exists('is_active', $data) && ! $data['is_active']);
        $others = User::where('role_id', $admin)->where('is_active', true)->whereKeyNot($user->id)->count();
        if ($losesAdmin && $others === 0) {
            throw ValidationException::withMessages(['role_id' => 'This is the last active Admin. Make another user Admin first.']);
        }
    }
}
