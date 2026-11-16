<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourceRegistry;
use App\Support\Uploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $query->orderBy($col, $dir)->orderBy('id', 'desc');

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
