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
