<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Attendance;
use App\Models\Business\Employee;
use App\Models\Business\Leave;
use App\Models\Business\Payroll;
use App\Models\Business\PayrollItem;
use App\Models\Business\TransactionCategory;
use App\Services\DocumentNumber;
use App\Services\Documents;
use App\Services\Ledger;
use App\Services\Money;
use App\Support\ActivityLogger;
use App\Support\BranchContext;
use App\Support\Csv;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & payroll. Days without an attendance mark count as present (mark only absences, leave,
 * half days and holidays). Salary per day = (basic + allowance) / working days of the month.
 */
class HrController extends Controller
{
    // ---------------- Employees ----------------

    public function employees(Request $request)
    {
        $q = Employee::query()->with(['branch:id,code', 'user:id,name'])
            ->when(! $request->boolean('inactive'), fn ($x) => $x->where('is_active', true))
            ->when(trim((string) $request->query('q')) !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')
                ->orWhere('phone', 'like', '%'.$request->query('q').'%')->orWhere('designation', 'like', '%'.$request->query('q').'%')))
            ->orderBy('name');

        return response()->json(['data' => $q->get()]);
    }

    public function showEmployee(Request $request, Employee $employee)
    {
        $month = $request->query('month') ?: now()->format('Y-m');
        $start = Carbon::parse($month.'-01');
        $att = Attendance::where('employee_id', $employee->id)->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->get();

        return response()->json([
            'data' => $employee->load(['branch:id,name,code', 'user:id,name,email']),
            'month' => $month,
            'attendance' => $att->mapWithKeys(fn ($a) => [$a->date->toDateString() => $a->status]),
            'leaves' => $employee->leaves()->limit(30)->get(),
            'payslips' => PayrollItem::where('employee_id', $employee->id)->whereHas('payroll', fn ($p) => $p->where('status', '!=', 'cancelled'))
                ->with('payroll:id,month,status')->latest('id')->limit(12)->get(['id', 'payroll_id', 'net']),
        ]);
    }

    public function saveEmployee(Request $request, ?Employee $employee = null)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'code' => 'nullable|string|max:30',
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'nid' => 'nullable|string|max:50',
            'join_date' => 'nullable|date',
            'basic_salary' => 'required|numeric|min:0|max:99999999',
            'allowance' => 'nullable|numeric|min:0|max:99999999',
            'user_id' => 'nullable|integer|exists:users,id',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ]);
        $data['allowance'] = $data['allowance'] ?? 0;
        if ($employee) {
            unset($data['branch_id']);
            $employee->update($data);
        } else {
            $data['branch_id'] = $data['branch_id'] ?? BranchContext::forWrite();
            $employee = Employee::create($data);
        }

        return response()->json(['message' => 'Employee saved', 'data' => $employee], $request->route('employee') ? 200 : 201);
    }

    // ---------------- Attendance ----------------

    /** One day: every active employee with their mark (unmarked = present). */
    public function attendanceDay(Request $request)
    {
        $date = $request->query('date') ?: now()->toDateString();
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'designation', 'branch_id']);
        $marks = Attendance::whereIn('employee_id', $employees->pluck('id'))->whereDate('date', $date)->get()->keyBy('employee_id');

        return response()->json(['date' => $date, 'data' => $employees->map(fn ($e) => [
            'employee_id' => $e->id, 'name' => $e->name, 'designation' => $e->designation,
            'status' => $marks[$e->id]->status ?? 'present', 'note' => $marks[$e->id]->note ?? null, 'marked' => isset($marks[$e->id]),
        ])]);
    }

    public function saveAttendance(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'rows' => 'required|array|min:1',
            'rows.*.employee_id' => 'required|integer|exists:employees,id',
            'rows.*.status' => ['required', Rule::in(Attendance::STATUSES)],
            'rows.*.note' => 'nullable|string|max:255',
        ]);
        $ids = Employee::whereIn('id', collect($data['rows'])->pluck('employee_id'))->pluck('id')->all(); // branch-scoped
        DB::transaction(function () use ($data, $ids, $request) {
            foreach ($data['rows'] as $r) {
                if (! in_array((int) $r['employee_id'], $ids, true)) {
                    continue;
                }
                Attendance::updateOrCreate(['employee_id' => $r['employee_id'], 'date' => $data['date']],
                    ['status' => $r['status'], 'note' => $r['note'] ?? null, 'marked_by' => $request->user()->id]);
            }
        });
        ActivityLogger::record('updated', null, 'Saved attendance for '.$data['date']);

        return response()->json(['message' => 'Attendance saved']);
    }

    /** Month grid: employee × day status + counts. */
    public function attendanceMonth(Request $request)
    {
        $month = $request->query('month') ?: now()->format('Y-m');
        $start = Carbon::parse($month.'-01');
        $end = $start->copy()->endOfMonth();
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'designation']);
        $marks = Attendance::whereIn('employee_id', $employees->pluck('id'))->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()->groupBy('employee_id');

        return response()->json(['month' => $month, 'days' => $end->day, 'data' => $employees->map(function ($e) use ($marks) {
            $m = ($marks[$e->id] ?? collect())->mapWithKeys(fn ($a) => [(int) $a->date->format('j') => $a->status]);

            return ['employee_id' => $e->id, 'name' => $e->name, 'designation' => $e->designation, 'marks' => $m,
                'absent' => $m->filter(fn ($s) => $s === 'absent')->count() + $m->filter(fn ($s) => $s === 'half_day')->count() * 0.5,
                'leave' => $m->filter(fn ($s) => $s === 'leave')->count()];
        })]);
    }

    // ---------------- Leave ----------------

    public function leaves(Request $request)
    {
        $q = Leave::query()->whereHas('employee')->with('employee:id,name,designation')
            ->when($request->filled('status'), fn ($x) => $x->where('status', $request->query('status')))
            ->latest('id');

        return response()->json($q->paginate(30));
    }

    public function storeLeave(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'type' => ['required', Rule::in(Leave::TYPES)],
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string|max:255',
        ]);
        Employee::findOrFail($data['employee_id']); // branch-scoped
        $data['days'] = Carbon::parse($data['from_date'])->diffInDays(Carbon::parse($data['to_date'])) + 1;
        $leave = Leave::create($data + ['status' => 'pending']);

        return response()->json(['message' => 'Leave request saved', 'data' => $leave], 201);
    }

    /** Approve or reject. Approval marks the days as "leave" in attendance. */
    public function decideLeave(Request $request, Leave $leave)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);
        Employee::findOrFail($leave->employee_id);
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This leave is already '.$leave->status.'.']);
        }
        DB::transaction(function () use ($leave, $data, $request) {
            $leave->update(['status' => $data['status'], 'decided_by' => $request->user()->id]);
            if ($data['status'] === 'approved') {
                foreach (CarbonPeriod::create($leave->from_date, $leave->to_date) as $day) {
                    Attendance::updateOrCreate(['employee_id' => $leave->employee_id, 'date' => $day->toDateString()],
                        ['status' => 'leave', 'note' => ucfirst($leave->type).' leave', 'marked_by' => $request->user()->id]);
                }
            }
        });

        return response()->json(['message' => 'Leave '.$data['status']]);
    }

    // ---------------- Payroll ----------------

    public function payrolls()
    {
        return response()->json(['data' => Payroll::with(['branch:id,code', 'account:id,name'])->withCount('items')->latest('month')->limit(60)->get()]);
    }

    public function showPayroll(Payroll $payroll)
    {
        return response()->json(['data' => $payroll->load(['items.employee:id,name,designation,code', 'account:id,name', 'branch:id,name,code'])]);
    }

    /** Create (or rebuild a draft) salary sheet for a month from employees + attendance + unpaid leave. */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'working_days' => 'nullable|integer|min:1|max:31',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        $branchId = BranchContext::forWrite() ?? ($data['branch_id'] ?? null);
        if (! $branchId || ! in_array((int) $branchId, BranchContext::allowed(), true)) {
            throw ValidationException::withMessages(['branch_id' => 'Choose a branch.']);
        }
        $start = Carbon::parse($data['month'].'-01');
        $end = $start->copy()->endOfMonth();
        // Default working days: all days except Fridays
        $working = $data['working_days'] ?? collect(CarbonPeriod::create($start, $end))->filter(fn ($d) => ! $d->isFriday())->count();

        $payroll = DB::transaction(function () use ($data, $branchId, $start, $end, $working, $request) {
            $p = Payroll::withoutGlobalScopes()->where('branch_id', $branchId)->where('month', $data['month'])->lockForUpdate()->first();
            if ($p && $p->status !== 'draft' && $p->status !== 'cancelled') {
                throw ValidationException::withMessages(['month' => "The salary sheet of {$data['month']} is already {$p->status}."]);
            }
            if (! $p) {
                $p = Payroll::create(['branch_id' => $branchId, 'number' => DocumentNumber::next('SAL', $branchId), 'month' => $data['month'],
                    'working_days' => $working, 'status' => 'draft', 'created_by' => $request->user()->id]);
            } else {
                $p->update(['working_days' => $working, 'status' => 'draft']);
                $p->items()->delete();
            }

            $employees = Employee::withoutGlobalScopes()->where('branch_id', $branchId)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('join_date')->orWhere('join_date', '<=', $end->toDateString()))->get();
            foreach ($employees as $e) {
                $marks = Attendance::where('employee_id', $e->id)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();
                $absent = $marks->where('status', 'absent')->count() + $marks->where('status', 'half_day')->count() * 0.5;
                $unpaid = (float) Leave::where('employee_id', $e->id)->where('status', 'approved')->where('type', 'unpaid')
                    ->where('from_date', '<=', $end->toDateString())->where('to_date', '>=', $start->toDateString())->get()
                    ->sum(fn ($l) => Carbon::parse(max($l->from_date->toDateString(), $start->toDateString()))->diffInDays(Carbon::parse(min($l->to_date->toDateString(), $end->toDateString()))) + 1);
                $item = new PayrollItem([
                    'payroll_id' => $p->id, 'employee_id' => $e->id, 'basic' => $e->basic_salary, 'allowance' => $e->allowance,
                    'absent_days' => $absent, 'unpaid_leave_days' => $unpaid, 'present_days' => max(0, $working - $absent - $unpaid),
                ]);
                self::compute($item, $working);
                $item->save();
            }
            $p->update(['total' => round((float) $p->items()->sum('net'), 2)]);

            return $p;
        });

        return response()->json(['message' => "Salary sheet {$payroll->month} ready (draft)", 'data' => $payroll], 201);
    }

    /** Bonus / advance / note per employee while the sheet is a draft. */
    public function updateItem(Request $request, Payroll $payroll, int $item)
    {
        if ($payroll->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft salary sheets can be changed.']);
        }
        $data = $request->validate(['bonus' => 'nullable|numeric|min:0', 'advance' => 'nullable|numeric|min:0', 'note' => 'nullable|string|max:255']);
        $row = PayrollItem::where('payroll_id', $payroll->id)->findOrFail($item);
        $row->fill(['bonus' => $data['bonus'] ?? 0, 'advance' => $data['advance'] ?? 0, 'note' => $data['note'] ?? null]);
        self::compute($row, $payroll->working_days);
        $row->save();
        $payroll->update(['total' => round((float) $payroll->items()->sum('net'), 2)]);

        return response()->json(['message' => 'Saved', 'data' => $row, 'total' => $payroll->total]);
    }

    public function finalize(Payroll $payroll)
    {
        if ($payroll->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only drafts can be finalized.']);
        }
        $payroll->update(['status' => 'finalized']);

        return response()->json(['message' => 'Salary sheet finalized', 'data' => $payroll]);
    }

    /** Pay the whole sheet from one account: one "Salary" expense in the ledger. */
    public function pay(Request $request, Payroll $payroll)
    {
        $data = $request->validate(['account_id' => 'required|integer|exists:accounts,id', 'date' => 'required|date|before_or_equal:today']);
        DB::transaction(function () use ($payroll, $data) {
            $p = Payroll::lockForUpdate()->findOrFail($payroll->id);
            if ($p->status !== 'finalized') {
                throw ValidationException::withMessages(['status' => 'Finalize the salary sheet before paying.']);
            }
            $account = Account::findOrFail($data['account_id']);
            if ((int) $account->branch_id !== (int) $p->branch_id) {
                throw ValidationException::withMessages(['account_id' => 'Pay from an account of the same branch.']);
            }
            if ($account->balance() < (float) $p->total) {
                throw ValidationException::withMessages(['account_id' => "Not enough balance in {$account->name} (".Money::format($account->balance()).').']);
            }
            Ledger::post(['type' => 'expense', 'account_id' => $account->id, 'amount' => $p->total, 'date' => $data['date'],
                'category_id' => TransactionCategory::idFor('salary'), 'description' => "Salary {$p->month} ({$p->number})"], $p);
            $p->update(['status' => 'paid', 'account_id' => $account->id, 'paid_date' => $data['date']]);
        });

        return response()->json(['message' => 'Salaries paid']);
    }

    /** Undo a payment (wrong account etc.): the ledger entry is cancelled, the sheet goes back to finalized. */
    public function unpay(Request $request, Payroll $payroll)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        if ($payroll->status !== 'paid') {
            throw ValidationException::withMessages(['status' => 'This salary sheet is not paid.']);
        }
        DB::transaction(function () use ($payroll, $data) {
            Ledger::cancelFor($payroll, 'Salary payment cancelled: '.$data['reason']);
            $payroll->update(['status' => 'finalized', 'account_id' => null, 'paid_date' => null]);
        });

        return response()->json(['message' => 'Payment cancelled; sheet is finalized again']);
    }

    public function payrollCsv(Payroll $payroll)
    {
        $payroll->load('items.employee');

        return Csv::download("salary-{$payroll->month}.csv", ['Employee', 'Designation', 'Basic', 'Allowance', 'Absent days', 'Unpaid leave', 'Deduction', 'Bonus', 'Advance', 'Net pay', 'Note'],
            $payroll->items->map(fn ($i) => [$i->employee?->name, $i->employee?->designation, $i->basic, $i->allowance, $i->absent_days, $i->unpaid_leave_days, $i->deduction, $i->bonus, $i->advance, $i->net, $i->note])->all());
    }

    /** Payslip PDF of one employee (uses the common document template). */
    public function payslip(Request $request, Payroll $payroll, int $item)
    {
        $row = PayrollItem::where('payroll_id', $payroll->id)->with('employee')->findOrFail($item);
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';
        $line = fn ($d, $a) => ['description' => $d, 'unit' => '', 'qty' => 1, 'rate' => $a, 'amount' => $a];
        $items = [$line('Basic salary', $row->basic)];
        if ((float) $row->allowance > 0) {
            $items[] = $line('Allowance', $row->allowance);
        }
        if ((float) $row->bonus > 0) {
            $items[] = $line('Bonus', $row->bonus);
        }
        $gross = (float) $row->basic + (float) $row->allowance + (float) $row->bonus;
        $doc = [
            'title' => 'Payslip', 'number' => $payroll->number.'-'.$row->employee_id, 'date' => $payroll->month,
            'meta' => ['Working days' => $payroll->working_days, 'Absent' => (float) $row->absent_days, 'Unpaid leave' => (float) $row->unpaid_leave_days],
            'party_label' => 'Employee', 'party' => array_values(array_filter([$row->employee?->name, $row->employee?->designation, $row->employee?->code])),
            'sections' => [['name' => 'Earnings', 'items' => $items]],
            'subtotal' => $gross, 'discount' => round((float) $row->deduction + (float) $row->advance, 2), 'tax_label' => '', 'tax' => 0, 'total' => $row->net,
            'notes' => 'Deduction = absence/unpaid leave '.Money::format($row->deduction).' + advance '.Money::format($row->advance).'.'.($row->note ? ' '.$row->note : ''),
            'hide_terms' => true,
        ];
        $pdf = Documents::pdf($doc, $lang);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="payslip-'.$payroll->month.'-'.$row->employee_id.'.pdf"']);
    }

    private static function compute(PayrollItem $i, int $working): void
    {
        $perDay = ((float) $i->basic + (float) $i->allowance) / max(1, $working);
        $i->deduction = round($perDay * ((float) $i->absent_days + (float) $i->unpaid_leave_days), 2);
        $i->net = round(max(0, (float) $i->basic + (float) $i->allowance - (float) $i->deduction + (float) $i->bonus - (float) $i->advance), 2);
    }
}
