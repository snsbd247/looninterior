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
        self::optimize(public_path($dir.'/'.$name), $ext);

        return $dir.'/'.$name;
    }

    /** Longest side after resizing; phone photos (4000px, 5-10 MB) become web-friendly. */
    public const MAX_SIDE = 1920;

    /**
     * Shrinks big JPG/PNG/WebP images to MAX_SIDE and re-compresses them (GD).
     * Small images and other formats (SVG, GIF) are left untouched. Never throws.
     */
    public static function optimize(string $path, string $ext): void
    {
        if (! function_exists('imagecreatetruecolor') || ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return;
        }
        try {
            [$w, $h] = @getimagesize($path) ?: [0, 0];
            $tooBig = max($w, $h) > self::MAX_SIDE;
            if (! $w || (! $tooBig && filesize($path) < 400 * 1024)) {
                return;
            }
            $src = match ($ext) {
                'png' => @imagecreatefrompng($path),
                'webp' => @imagecreatefromwebp($path),
                default => @imagecreatefromjpeg($path),
            };
            if (! $src) {
                return;
            }
            $scale = $tooBig ? self::MAX_SIDE / max($w, $h) : 1;
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            if ($ext !== 'jpg' && $ext !== 'jpeg') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $tmp = $path.'.tmp';
            $ok = match ($ext) {
                'png' => imagepng($dst, $tmp, 8),
                'webp' => imagewebp($dst, $tmp, 82),
                default => imagejpeg($dst, $tmp, 82),
            };
            imagedestroy($src);
            imagedestroy($dst);
            // Keep the smaller file only
            if ($ok && is_file($tmp) && filesize($tmp) < filesize($path)) {
                rename($tmp, $path);
            } else {
                @unlink($tmp);
            }
        } catch (\Throwable) {
            // Optimisation is best-effort; the original upload stays.
        }
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
