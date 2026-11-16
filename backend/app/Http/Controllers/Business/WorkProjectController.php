<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\Customer;
use App\Models\Business\Invoice;
use App\Models\Business\PaymentSchedule;
use App\Models\Business\ProjectFile;
use App\Models\Business\ProjectMilestone;
use App\Models\Business\ProjectTask;
use App\Models\Business\Quotation;
use App\Models\Business\Transaction;
use App\Models\Business\WorkProject;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Services\DocumentNumber;
use App\Support\ActivityLogger;
use App\Support\BranchContext;
use App\Support\Uploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkProjectController extends Controller
{
    public const DEFAULT_MILESTONES = ['Design & Approval', 'Civil & Ceiling', 'Furniture & Woodwork', 'Paint & Finishing', 'Handover'];
    public const DEFAULT_SCHEDULE = [['Advance', 50], ['During work', 30], ['On handover', 20]];

    public function index(Request $request)
    {
        $q = WorkProject::query()->with(['customer:id,name,phone', 'manager:id,name', 'branch:id,code']);
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")));
        }
        foreach (['status', 'customer_id', 'manager_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->boolean('all')) {
            return response()->json(['data' => $q->orderBy('code')->limit(1000)->get(['id', 'code', 'title', 'customer_id', 'branch_id', 'status'])]);
        }

        return response()->json($q->latest('id')->paginate(min(100, max(1, (int) $request->query('per_page', 20)))));
    }

    public function show(WorkProject $project)
    {
        $project->load([
            'customer', 'quotation:id,number,total,status', 'manager:id,name', 'branch:id,name,code',
            'milestones', 'tasks.assignee:id,name', 'tasks.milestone:id,name', 'files.uploader:id,name',
            'schedules.invoice:id,number,status,total,paid,credited', 'portfolio:id,title,slug',
        ]);
        $invoices = Invoice::where('work_project_id', $project->id)->latest('id')->get();
        $expenses = Transaction::where('work_project_id', $project->id)->where('status', 'active')->where('type', 'expense')
            ->with('category:id,name')->latest('date')->limit(100)->get();

        return response()->json([
            'data' => $project,
            'invoices' => $invoices,
            'expenses' => $expenses,
            'finance' => self::finance($project),
        ]);
    }

    /** Contract, billing, collection and cost summary (materials are added by the inventory module). */
    public static function finance(WorkProject $p): array
    {
        $inv = Invoice::where('work_project_id', $p->id)->where('status', '!=', 'cancelled');
        $invoiced = (float) (clone $inv)->sum('total');
        $received = (float) (clone $inv)->sum('paid');
        $credited = (float) (clone $inv)->sum('credited');
        $expenses = (float) Transaction::where('work_project_id', $p->id)->where('status', 'active')->where('type', 'expense')->sum('amount');
        $materials = class_exists(\App\Services\Stock::class) ? \App\Services\Stock::projectMaterialCost($p->id) : 0.0;
        $labour = (float) \App\Models\Business\LabourEntry::where('work_project_id', $p->id)->where('status', 'active')->sum('amount');
        $cost = round($expenses + $materials + $labour, 2);

        return [
            'contract' => (float) $p->contract_value,
            'invoiced' => round($invoiced, 2),
            'received' => round($received, 2),
            'credited' => round($credited, 2),
            'receivable' => round($invoiced - $received - $credited, 2),
            'expenses' => round($expenses, 2),
            'materials' => round($materials, 2),
            'labour' => round($labour, 2),
            'cost' => $cost,
            'profit' => round((float) $p->contract_value - $cost, 2),
            'cash_profit' => round($received - $cost, 2),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $quotation = ! empty($data['quotation_id']) ? Quotation::findOrFail($data['quotation_id']) : null;
        if ($quotation && $quotation->status !== 'approved') {
            throw ValidationException::withMessages(['quotation_id' => 'Only an approved quotation can start a project.']);
        }
        $customer = Customer::findOrFail($data['customer_id'] ?? $quotation?->customer_id);
        $branchId = $quotation?->branch_id ?? BranchContext::forWrite() ?? $customer->branch_id;
        if ((int) $customer->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['customer_id' => 'The customer belongs to another branch.']);
        }

        $project = DB::transaction(function () use ($data, $quotation, $customer, $branchId, $request) {
            $p = WorkProject::create([
                'branch_id' => $branchId,
                'code' => DocumentNumber::next('PRJ', $branchId),
                'customer_id' => $customer->id,
                'quotation_id' => $quotation?->id,
                'title' => $data['title'] ?? $quotation?->title ?? $customer->name.' Interior',
                'site_address' => $data['site_address'] ?? $customer->address,
                'contract_value' => $data['contract_value'] ?? $quotation?->total ?? 0,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'status' => $data['status'] ?? 'planning',
                'description' => $data['description'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            foreach (self::DEFAULT_MILESTONES as $i => $name) {
                $p->milestones()->create(['name' => $name, 'sort_order' => $i]);
            }
            if ((float) $p->contract_value > 0) {
                $this->buildSchedule($p, collect(self::DEFAULT_SCHEDULE)->map(fn ($s) => ['label' => $s[0], 'percent' => $s[1]])->all());
            }

            return $p;
        });

        return response()->json(['message' => "Project {$project->code} created", 'data' => $project], 201);
    }

    public function update(Request $request, WorkProject $project)
    {
        $data = $this->validated($request, true);
        unset($data['quotation_id'], $data['customer_id']);
        $project->update($data);

        return response()->json(['message' => 'Project updated', 'data' => $project]);
    }

    // ---------------- Milestones & tasks ----------------

    public function saveMilestones(Request $request, WorkProject $project)
    {
        $data = $request->validate([
            'milestones' => 'present|array|max:50',
            'milestones.*.id' => 'nullable|integer',
            'milestones.*.name' => 'required|string|max:255',
            'milestones.*.progress' => 'required|integer|min:0|max:100',
            'milestones.*.due_date' => 'nullable|date',
        ]);
        DB::transaction(function () use ($project, $data) {
            $keep = [];
            foreach ($data['milestones'] as $i => $m) {
                $row = ! empty($m['id']) ? ProjectMilestone::where('work_project_id', $project->id)->find($m['id']) : null;
                $row ??= new ProjectMilestone(['work_project_id' => $project->id]);
                $row->fill(['name' => $m['name'], 'progress' => $m['progress'], 'due_date' => $m['due_date'] ?? null, 'sort_order' => $i])->save();
                $keep[] = $row->id;
            }
            ProjectMilestone::where('work_project_id', $project->id)->whereNotIn('id', $keep)->delete();
            $project->refreshProgress();
        });
        ActivityLogger::record('updated', $project, "Updated milestones of {$project->code}");

        return response()->json(['message' => 'Milestones saved', 'data' => $project->fresh('milestones')]);
    }

    public function saveTask(Request $request, WorkProject $project, ?int $task = null)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:3000',
            'milestone_id' => ['nullable', 'integer', Rule::exists('project_milestones', 'id')->where('work_project_id', $project->id)],
            'assigned_to' => 'nullable|integer|exists:users,id',
            'due_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(ProjectTask::STATUSES)],
        ]);
        $row = $task ? ProjectTask::where('work_project_id', $project->id)->findOrFail($task) : new ProjectTask(['work_project_id' => $project->id, 'created_by' => $request->user()->id]);
        $row->fill($data + ['status' => $row->status ?? 'todo'])->save();

        return response()->json(['message' => 'Task saved', 'data' => $row->load('assignee:id,name')], $task ? 200 : 201);
    }

    public function deleteTask(WorkProject $project, int $task)
    {
        ProjectTask::where('work_project_id', $project->id)->findOrFail($task)->delete();

        return response()->json(['message' => 'Task removed']);
    }

    // ---------------- Files ----------------

    public function uploadFiles(Request $request, WorkProject $project)
    {
        $request->validate([
            'files' => 'required|array|max:20',
            'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,dwg,zip',
        ]);
        $saved = [];
        foreach ($request->file('files') as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
            if ($isImage) {
                $path = Uploader::store($file, 'work-projects');
            } else {
                $name = date('Ymd').'-'.Str::random(20).'.'.strtolower($file->guessExtension() ?: $ext);
                $file->move(storage_path('app/project-files'), $name);
                $path = 'project-files/'.$name;
            }
            $saved[] = ProjectFile::create([
                'work_project_id' => $project->id, 'kind' => $isImage ? 'image' : 'doc', 'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'size' => (int) $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
        ActivityLogger::record('updated', $project, 'Uploaded '.count($saved)." file(s) to {$project->code}");

        return response()->json(['message' => count($saved).' file(s) uploaded', 'data' => $saved], 201);
    }

    public function downloadFile(WorkProject $project, int $file)
    {
        $f = ProjectFile::where('work_project_id', $project->id)->findOrFail($file);
        if ($f->kind === 'image') {
            return response()->download(public_path($f->path), $f->original_name);
        }
        abort_if(str_contains($f->path, '..'), 404);

        return response()->download(storage_path('app/'.$f->path), $f->original_name);
    }

    public function deleteFile(WorkProject $project, int $file)
    {
        $f = ProjectFile::where('work_project_id', $project->id)->findOrFail($file);
        $f->kind === 'image' ? Uploader::delete($f->path) : @unlink(storage_path('app/'.$f->path));
        $f->delete();
        ActivityLogger::record('updated', $project, "Removed file \"{$f->original_name}\" from {$project->code}");

        return response()->json(['message' => 'File removed']);
    }

    // ---------------- Payment schedule ----------------

    public function saveSchedule(Request $request, WorkProject $project)
    {
        $data = $request->validate([
            'schedules' => 'present|array|max:20',
            'schedules.*.label' => 'required|string|max:255',
            'schedules.*.percent' => 'nullable|numeric|min:0|max:100',
            'schedules.*.amount' => 'nullable|numeric|min:0',
            'schedules.*.due_date' => 'nullable|date',
        ]);
        if (PaymentSchedule::where('work_project_id', $project->id)->whereNotNull('invoice_id')->exists()) {
            throw ValidationException::withMessages(['schedules' => 'Some installments are already invoiced. Edit is locked; raise remaining invoices manually.']);
        }
        DB::transaction(fn () => $this->buildSchedule($project, $data['schedules']));
        ActivityLogger::record('updated', $project, "Updated payment schedule of {$project->code}");

        return response()->json(['message' => 'Payment schedule saved', 'data' => $project->fresh('schedules')->schedules]);
    }

    private function buildSchedule(WorkProject $p, array $rows): void
    {
        PaymentSchedule::where('work_project_id', $p->id)->delete();
        foreach (array_values($rows) as $i => $s) {
            $percent = isset($s['percent']) && $s['percent'] !== '' ? (float) $s['percent'] : null;
            $amount = $percent !== null ? round((float) $p->contract_value * $percent / 100, 2) : round((float) ($s['amount'] ?? 0), 2);
            PaymentSchedule::create([
                'work_project_id' => $p->id, 'label' => $s['label'], 'percent' => $percent, 'amount' => $amount,
                'due_date' => $s['due_date'] ?? null, 'sort_order' => $i,
            ]);
        }
    }

    // ---------------- Website portfolio ----------------

    /** Publish the finished job to the website portfolio (photos become the gallery). */
    public function publish(Request $request, WorkProject $project)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'project_category_id' => 'nullable|integer|exists:project_categories,id',
            'short_description' => 'nullable|string|max:1000',
            'description' => 'nullable|string|max:20000',
            'location' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'show_client' => 'boolean',
        ]);
        $images = ProjectFile::where('work_project_id', $project->id)->where('kind', 'image')->orderBy('id')->get();
        if ($images->isEmpty()) {
            throw ValidationException::withMessages(['images' => 'Upload project photos first (Files tab).']);
        }
        if ($project->portfolio_project_id && Project::find($project->portfolio_project_id)) {
            throw ValidationException::withMessages(['title' => 'Already published. Edit it from Website → Projects.']);
        }

        $portfolio = DB::transaction(function () use ($project, $data, $images) {
            $base = Str::slug($data['title']) ?: 'project-'.$project->id;
            $slug = $base;
            $i = 2;
            while (Project::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }
            $cover = Uploader::copy($images->first()->path, 'projects');
            $web = Project::create([
                'project_category_id' => $data['project_category_id'] ?? null,
                'title' => $data['title'],
                'slug' => $slug,
                'client_name' => ! empty($data['show_client']) ? $project->customer?->name : null,
                'location' => $data['location'] ?? null,
                'area' => $data['area'] ?? null,
                'year' => (string) ($project->handover_date?->year ?? now()->year),
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'cover_image' => $cover,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 0,
            ]);
            foreach ($images->slice(1)->values() as $n => $img) {
                ProjectImage::create(['project_id' => $web->id, 'image' => Uploader::copy($img->path, 'projects/gallery'), 'sort_order' => $n + 1]);
            }
            $project->update(['portfolio_project_id' => $web->id]);

            return $web;
        });

        return response()->json(['message' => 'Published to website', 'data' => $portfolio], 201);
    }

    private function validated(Request $request, bool $update = false): array
    {
        return $request->validate([
            'quotation_id' => 'nullable|integer|exists:quotations,id',
            'customer_id' => ($update ? 'nullable' : 'required_without:quotation_id').'|nullable|integer|exists:customers,id',
            'title' => ($update ? 'required' : 'nullable').'|string|max:255',
            'site_address' => 'nullable|string|max:1000',
            'contract_value' => 'nullable|numeric|min:0|max:999999999999',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'handover_date' => 'nullable|date',
            'warranty_until' => 'nullable|date',
            'manager_id' => 'nullable|integer|exists:users,id',
            'status' => ['nullable', Rule::in(WorkProject::STATUSES)],
            'progress' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string|max:5000',
        ]);
    }
}
