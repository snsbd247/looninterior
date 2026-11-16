<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Customer;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    /** Relations that exist so far (later steps add quotations, projects, invoices…). */
    private function related(): array
    {
        return array_values(array_filter(
            ['quotations', 'projects', 'invoices', 'payments', 'tickets'],
            fn ($rel) => class_exists('App\\Models\\Business\\'.[
                'quotations' => 'Quotation', 'projects' => 'WorkProject', 'invoices' => 'Invoice',
                'payments' => 'Payment', 'tickets' => 'Ticket',
            ][$rel])
        ));
    }

    public function index(Request $request)
    {
        $q = Customer::query()->with('branch:id,code')->withCount(array_merge(['leads'], $this->related()));
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")->orWhere('company', 'like', "%{$s}%"));
        }
        if ($request->boolean('all')) {
            return response()->json(['data' => $q->orderBy('name')->limit(1000)->get(['id', 'name', 'phone', 'email', 'address', 'branch_id'])]);
        }

        return response()->json($q->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20)))));
    }

    public function show(Customer $customer)
    {
        $with = ['branch:id,name,code', 'leads' => fn ($q) => $q->latest('id')->with('assignee:id,name')];
        foreach ($this->related() as $rel) {
            $with[$rel] = fn ($q) => $q->latest('id')->limit(50);
        }

        return response()->json(['data' => $customer->load($with)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $customer = Customer::create($data);

        return response()->json(['message' => 'Customer created', 'data' => $customer], 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validated($request);
        unset($data['branch_id']);
        $customer->update($data);

        return response()->json(['message' => 'Customer updated', 'data' => $customer]);
    }

    /** Client portal access: turn on/off and set a password (perm portal.manage). Turning off signs the customer out. */
    public function portal(Request $request, Customer $customer)
    {
        $data = $request->validate(['enabled' => 'required|boolean', 'password' => 'nullable|string|min:8|max:100']);
        if ($data['enabled']) {
            if (! $customer->phone && ! $customer->email) {
                throw ValidationException::withMessages(['enabled' => 'Add a phone number or e-mail first; the customer signs in with it.']);
            }
            if (empty($data['password']) && ! $customer->portal_password) {
                throw ValidationException::withMessages(['password' => 'Set a password (at least 8 characters).']);
            }
        }
        $customer->forceFill(array_filter([
            'portal_enabled' => (bool) $data['enabled'],
            'portal_password' => ! empty($data['password']) ? Hash::make($data['password']) : null,
        ], fn ($v) => $v !== null))->save();
        if (! $data['enabled'] || ! empty($data['password'])) {
            $customer->tokens()->delete();
        }
        ActivityLogger::record('portal', $customer, $data['enabled'] ? 'Client portal enabled'.(! empty($data['password']) ? ' (password set)' : '') : 'Client portal disabled');

        return response()->json(['message' => $data['enabled'] ? 'Client portal enabled' : 'Client portal disabled',
            'data' => $customer->only(['id', 'portal_enabled', 'portal_last_login'])]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);
    }
}
