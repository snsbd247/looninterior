<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Account;
use App\Models\Business\Customer;
use App\Models\Business\Employee;
use App\Models\Business\Lead;
use App\Models\Business\LeadActivity;
use App\Models\Business\Leave;
use App\Models\Business\Payroll;
use App\Models\Business\ProjectFile;
use App\Models\Business\ProjectMilestone;
use App\Models\Business\PurchaseOrder;
use App\Models\Business\Quotation;
use App\Models\Business\Store;
use App\Models\Business\Ticket;
use App\Models\Business\WorkProject;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\SafeDelete;
use App\Support\Uploader;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Delete buttons for business records (phase G).
 *
 * Rules (CLAUDE.md): a record is deleted only when nothing else uses it (SafeDelete); documents only while still a
 * draft / pending / open. Invoices, payments, stock and ledger entries are never deleted: they have Cancel instead.
 * Route model binding runs after the branch middleware, so another branch's record is a 404.
 */
class DeleteController extends Controller
{
    private function ok(string $what)
    {
        return response()->json(['message' => ucfirst($what).' deleted']);
    }

    private function onlyWhen(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['delete' => $message]);
        }
    }

    public function user(Request $request, User $user)
    {
        $this->onlyWhen($user->id !== $request->user()->id, 'You cannot delete your own account.');
        $adminRoles = \App\Models\Business\Role::where('is_system', true)->pluck('id');
        if ($adminRoles->contains($user->role_id)) {
            $this->onlyWhen(User::whereIn('role_id', $adminRoles)->where('is_active', true)->where('id', '!=', $user->id)->exists(),
                'This is the last active Admin. Make another user Admin first.');
        }
        SafeDelete::delete($user, 'user', 'Turn the user off instead (Edit → Active), so the history keeps their name.',
            before: fn ($u) => $u->tokens()->delete());

        return $this->ok('user');
    }

    public function lead(Lead $lead)
    {
        SafeDelete::delete($lead, 'lead', 'Move it to Lost instead.');

        return $this->ok('lead');
    }

    public function leadActivity(LeadActivity $activity)
    {
        $activity->lead()->firstOrFail(); // branch check through the lead
        $activity->delete();

        return $this->ok('follow-up');
    }

    public function customer(Customer $customer)
    {
        SafeDelete::delete($customer, 'customer', 'Customers with history are kept for the records.',
            before: fn ($c) => $c->tokens()->delete());

        return $this->ok('customer');
    }

    public function quotation(Quotation $quotation)
    {
        $this->onlyWhen($quotation->status === 'draft', 'Only a draft quotation can be deleted. Mark it Rejected instead.');
        SafeDelete::delete($quotation, 'quotation', 'Mark it Rejected instead.');

        return $this->ok('quotation');
    }

    public function project(WorkProject $project)
    {
        $files = ProjectFile::where('work_project_id', $project->id)->get(['kind', 'path']);
        SafeDelete::delete($project, 'project', 'Set its status to Cancelled instead.', after: function () use ($files) {
            foreach ($files as $f) {
                $f->kind === 'image' ? Uploader::delete($f->path) : @unlink(storage_path('app/'.$f->path));
            }
        });

        return $this->ok('project');
    }

    public function milestone(WorkProject $project, int $milestone)
    {
        $m = ProjectMilestone::where('work_project_id', $project->id)->findOrFail($milestone);
        $m->delete(); // its tasks stay, without a stage
        ActivityLogger::record('updated', $project, "Removed stage \"{$m->name}\" from {$project->code}");

        return $this->ok('stage');
    }

    public function account(Account $account)
    {
        SafeDelete::delete($account, 'account', 'Mark it inactive instead.');

        return $this->ok('account');
    }

    public function store(Store $store)
    {
        SafeDelete::delete($store, 'store', 'Mark it inactive instead.');

        return $this->ok('store');
    }

    public function employee(Employee $employee)
    {
        SafeDelete::delete($employee, 'employee', 'Mark the employee as Left (inactive) instead.');

        return $this->ok('employee');
    }

    public function leave(Leave $leave)
    {
        $leave->employee()->firstOrFail();
        $this->onlyWhen($leave->status === 'pending', 'Only a pending request can be deleted. An approved leave is already in the attendance.');
        $leave->delete();

        return $this->ok('leave request');
    }

    public function payroll(Payroll $payroll)
    {
        $this->onlyWhen($payroll->status === 'draft', 'Only a draft salary sheet can be deleted.');
        SafeDelete::delete($payroll, 'salary sheet');

        return $this->ok('salary sheet');
    }

    public function purchaseOrder(PurchaseOrder $order)
    {
        $this->onlyWhen($order->status === 'draft', 'Only a draft purchase order can be deleted. Cancel it instead.');
        SafeDelete::delete($order, 'purchase order', 'Cancel it instead.');

        return $this->ok('purchase order');
    }

    public function ticket(Ticket $ticket)
    {
        $this->onlyWhen($ticket->status === 'open', 'Only an open ticket (no work started) can be deleted. Close it instead.');
        SafeDelete::delete($ticket, 'ticket', 'Close it instead.');

        return $this->ok('ticket');
    }
}
