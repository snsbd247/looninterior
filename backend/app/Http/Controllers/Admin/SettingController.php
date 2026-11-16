<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Uploader;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Setting::allAsArray()]);
    }

    public function update(Request $request)
    {
        $fileKeys = array_keys($request->allFiles());

        $rules = [];
        foreach ($fileKeys as $key) {
            $rules[$key] = 'file|mimes:jpg,jpeg,png,webp,gif,svg,ico|max:8192';
        }
        $request->validate($rules);

        $current = Setting::allAsArray();

        // Text values
        foreach ($request->except($fileKeys) as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[a-z0-9_]{1,100}$/', $key)) {
                continue;
            }

            if (str_starts_with($key, 'remove_')) {
                $target = substr($key, 7);
                if ($value && ! in_array($target, $fileKeys, true)) {
                    Uploader::delete($current[$target] ?? null);
                    Setting::updateOrCreate(['key' => $target], ['value' => null]);
                }
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value) : $value]
            );
        }

        // Image values
        foreach ($fileKeys as $key) {
            if (! preg_match('/^[a-z0-9_]{1,100}$/', $key)) {
                continue;
            }
            Uploader::delete($current[$key] ?? null);
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => Uploader::store($request->file($key), 'settings')]
            );
        }

        return response()->json(['message' => 'Settings saved', 'data' => Setting::allAsArray()]);
    }
}
