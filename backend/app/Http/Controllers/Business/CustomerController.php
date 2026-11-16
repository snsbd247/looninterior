<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Customer;
use Illuminate\Http\Request;

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
