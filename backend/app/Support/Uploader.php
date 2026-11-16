<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores uploads directly inside public/uploads so no `storage:link`
 * symlink is required on shared hosting.
 */
class Uploader
{
    public static function store(UploadedFile $file, string $folder): string
    {
        $dir = 'uploads/'.trim($folder, '/');
        // Extension is derived from the real file content, never from the client name.
        $ext = strtolower($file->guessExtension() ?: 'bin');
        $name = date('Ymd').'-'.Str::random(16).'.'.$ext;
        $file->move(public_path($dir), $name);

        return $dir.'/'.$name;
    }

    /** Copy an existing upload into another folder (e.g. a project photo into the website portfolio). */
    public static function copy(string $path, string $folder): string
    {
        abort_if(! str_starts_with($path, 'uploads/') || str_contains($path, '..') || ! is_file(public_path($path)), 422, 'Image not found.');
        $dir = 'uploads/'.trim($folder, '/');
        if (! is_dir(public_path($dir))) {
            @mkdir(public_path($dir), 0775, true);
        }
        $name = date('Ymd').'-'.Str::random(16).'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
        copy(public_path($path), public_path($dir.'/'.$name));

        return $dir.'/'.$name;
    }

    public static function delete(?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
            return;
        }
        $full = public_path($path);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
