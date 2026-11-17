<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourceRegistry;
use App\Support\Uploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResourceController extends Controller
{
    protected function config(string $resource): array
    {
        $cfg = ResourceRegistry::get($resource);
        abort_if(! $cfg, 404, 'Unknown resource');
        abort_unless(
            request()->user()?->hasPermission($cfg['permission'] ?? 'website.manage'),
            403,
            'You do not have permission to do this.'
        );

        return $cfg;
    }

    public function index(Request $request, string $resource)
    {
        $cfg = $this->config($resource);
        $query = $cfg['model']::query();

        if (! empty($cfg['with'])) {
            $query->with($cfg['with']);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && ! empty($cfg['search'])) {
            $query->where(function ($w) use ($cfg, $q) {
                foreach ($cfg['search'] as $col) {
                    $w->orWhere($col, 'like', '%'.$q.'%');
                }
            });
        }

        foreach ($cfg['filters'] ?? [] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        [$col, $dir] = $cfg['order'] ?? ['id', 'desc'];
        $query->orderBy($col, $dir);
        foreach ($cfg['then_order'] ?? [] as [$c, $d]) { // optional extra sort columns
            $query->orderBy($c, $d);
        }
        $query->orderBy('id', 'desc');

        // Excel/CSV export of the filtered list (registry key "csv" => [column => header])
        if ($request->query('format') === 'csv' && ! empty($cfg['csv'])) {
            $rows = $query->limit(20000)->get();

            return \App\Support\Csv::download($resource.'-'.date('Y-m-d').'.csv', array_values($cfg['csv']),
                $rows->map(fn ($r) => array_map(fn ($col) => data_get($r, $col) instanceof \DateTimeInterface ? data_get($r, $col)->format('Y-m-d H:i') : data_get($r, $col), array_keys($cfg['csv'])))->all());
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return response()->json($query->paginate($perPage));
    }

    public function show(string $resource, int $id)
    {
        $cfg = $this->config($resource);
        $model = $cfg['model']::findOrFail($id);

        return response()->json(['data' => $this->loadRelations($cfg, $model)]);
    }

    public function store(Request $request, string $resource)
    {
        $cfg = $this->config($resource);
        abort_if(! empty($cfg['readonly']), 405, 'Records of this type are created from the website.');

        $model = new $cfg['model'];
        $this->save($request, $cfg, $model, $resource);

        return response()->json([
            'message' => 'Saved successfully',
            'data' => $this->loadRelations($cfg, $model),
        ], 201);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $cfg = $this->config($resource);
        $model = $cfg['model']::findOrFail($id);
        $this->save($request, $cfg, $model, $resource);

        return response()->json([
            'message' => 'Updated successfully',
            'data' => $this->loadRelations($cfg, $model->fresh()),
        ]);
    }

    public function destroy(string $resource, int $id)
    {
        $cfg = $this->config($resource);
        abort_if(! empty($cfg['no_delete']), 405, 'This record cannot be deleted. Mark it inactive instead.');
        $model = $cfg['model']::findOrFail($id);

        if (! empty($cfg['safe_delete'])) {
            return $this->safeDestroy($resource, $model);
        }

        foreach ($cfg['files'] ?? [] as $field) {
            Uploader::delete($model->getAttribute($field));
        }
        foreach ($cfg['private_files'] ?? [] as $field) {
            $path = $model->getAttributes()[$field] ?? null;
            if ($path && ! str_contains($path, '..') && is_file(storage_path('app/'.$path))) {
                @unlink(storage_path('app/'.$path));
            }
        }
        if (! empty($cfg['gallery'])) {
            $relation = $cfg['gallery'];
            foreach ($model->$relation as $image) {
                Uploader::delete($image->image);
            }
            $model->$relation()->delete();
        }

        $model->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    /** Business master data: delete only when unused (SafeDelete), with a few records that must always stay. */
    protected function safeDestroy(string $resource, Model $model)
    {
        $what = str_replace('_', ' ', \Illuminate\Support\Str::singular($resource));
        $before = null;
        $ignore = [];
        if ($resource === 'transaction_categories' && $model->is_system) {
            throw ValidationException::withMessages(['delete' => 'This is a system category used by the software. Mark it inactive instead.']);
        }
        if ($resource === 'branches') {
            if (\App\Models\Business\Branch::count() <= 1) {
                throw ValidationException::withMessages(['delete' => 'The last branch cannot be deleted.']);
            }
            if ((string) (\App\Models\Setting::businessArray()['biz_lead_branch_id'] ?? '') === (string) $model->id) {
                throw ValidationException::withMessages(['delete' => 'Website leads go to this branch (Business Settings → CRM). Choose another branch there first.']);
            }
            $ignore = ['activity_logs']; // the log text stays; its branch becomes empty (nullOnDelete)
            // A new branch gets a Cash account and a Main Store automatically: remove them if they were never used.
            $before = function ($b) {
                foreach (\App\Models\Business\Account::withoutGlobalScopes()->where('branch_id', $b->id)->get() as $a) {
                    if (! \App\Support\SafeDelete::usage($a) && (float) $a->opening_balance == 0.0) {
                        $a->delete();
                    }
                }
                foreach (\App\Models\Business\Store::withoutGlobalScopes()->where('branch_id', $b->id)->get() as $s) {
                    if (! \App\Support\SafeDelete::usage($s)) {
                        $s->delete();
                    }
                }
            };
        }
        // Branch access of users is a cascade pivot, but losing it would lock users out: it must block.
        $block = $resource === 'branches' ? ['branch_user'] : [];
        $hint = $resource === 'branches' ? 'Remove the branch from those users first (Users → Edit), or mark it inactive.' : 'Mark it inactive instead.';
        \App\Support\SafeDelete::delete($model, $what, $hint, $block, $ignore, $before);

        return response()->json(['message' => 'Deleted successfully']);
    }

    protected function loadRelations(array $cfg, Model $model): Model
    {
        $with = $cfg['with'] ?? [];
        if (! empty($cfg['gallery'])) {
            $with[] = $cfg['gallery'];
        }

        return $with ? $model->load($with) : $model;
    }

    protected function save(Request $request, array $cfg, Model $model, string $resource): void
    {
        $id = $model->exists ? (string) $model->getKey() : 'NULL';

        $rules = [];
        foreach ($cfg['rules'] as $field => $rule) {
            $rules[$field] = str_replace('{id}', $id, $rule);
        }

        $files = $cfg['files'] ?? [];
        foreach ($files as $field) {
            $rules[$field] = 'nullable|file|mimes:jpg,jpeg,png,webp,gif,svg|max:8192';
        }

        if (! empty($cfg['gallery'])) {
            $rules['gallery'] = 'nullable|array';
            $rules['gallery.*'] = 'file|mimes:jpg,jpeg,png,webp,gif|max:8192';
            $rules['remove_gallery'] = 'nullable|array';
            $rules['remove_gallery.*'] = 'integer';
        }

        $data = $request->validate($rules);
        unset($data['gallery'], $data['remove_gallery']);

        // Image columns
        foreach ($files as $field) {
            unset($data[$field]);
            if ($request->hasFile($field)) {
                Uploader::delete($model->getAttribute($field));
                $data[$field] = Uploader::store($request->file($field), $resource);
            } elseif ($request->boolean('remove_'.$field)) {
                Uploader::delete($model->getAttribute($field));
                $data[$field] = null;
            }
        }

        // Checkboxes
        foreach ($cfg['booleans'] ?? [] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->boolean($field);
            }
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }

        // Slug
        if (! empty($cfg['slug'])) {
            $source = $cfg['slug'];
            $base = ($data['slug'] ?? null) ?: ($model->getAttribute('slug') ?: ($data[$source] ?? $model->getAttribute($source)));
            $data['slug'] = $this->uniqueSlug($cfg['model'], (string) $base, $model->getKey());
        }

        $model->fill($data)->save();

        // Gallery images
        if (! empty($cfg['gallery'])) {
            $relation = $cfg['gallery'];

            foreach ((array) $request->input('remove_gallery', []) as $imageId) {
                $image = $model->$relation()->whereKey($imageId)->first();
                if ($image) {
                    Uploader::delete($image->image);
                    $image->delete();
                }
            }

            $max = (int) $model->$relation()->max('sort_order');
            foreach ((array) $request->file('gallery', []) as $file) {
                $model->$relation()->create([
                    'image' => Uploader::store($file, $resource.'/gallery'),
                    'sort_order' => ++$max,
                ]);
            }
        }
    }

    protected function uniqueSlug(string $modelClass, string $text, $ignoreId = null): string
    {
        $slug = Str::slug($text);
        if ($slug === '') {
            // Non-latin titles (e.g. Bangla) – keep unicode letters
            $slug = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($text)), '-');
        }
        if ($slug === '') {
            $slug = Str::lower(Str::random(8));
        }

        $original = $slug;
        $i = 2;
        while ($modelClass::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $original.'-'.$i++;
        }

        return $slug;
    }
}
