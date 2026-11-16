<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
| One-time installer for cPanel without Terminal:
| set INSTALL_KEY in .env, visit /install/<key>, then clear INSTALL_KEY.
*/
Route::get('/install/{key}', function (string $key) {
    $expected = (string) config('site.install_key');
    abort_unless($expected !== '' && hash_equals($expected, $key), 404);

    Artisan::call('migrate', ['--force' => true]);
    $out = Artisan::output();

    if (User::count() === 0) {
        Artisan::call('db:seed', ['--force' => true]);
        $out .= Artisan::output();
    }

    return response('<pre>'.e($out)."\nDone. Now remove INSTALL_KEY from .env</pre>");
});

/*
| Serve the React single page app (built into public/build) for every
| non-API URL. Basic SEO meta tags are injected from the settings table.
*/
$spa = function () {
    $file = public_path('build/index.html');
    if (! is_file($file)) {
        return response('Frontend build not found. Run "npm run build" inside the frontend folder.', 503);
    }

    $html = file_get_contents($file);

    try {
        $s = Setting::allAsArray();
        $title = e($s['meta_title'] ?? $s['site_name'] ?? config('app.name'));
        $desc = e($s['meta_description'] ?? '');
        $keywords = e($s['meta_keywords'] ?? '');
        $meta = "<title>{$title}</title>\n"
            ."    <meta name=\"description\" content=\"{$desc}\">\n"
            ."    <meta name=\"keywords\" content=\"{$keywords}\">\n"
            ."    <meta property=\"og:title\" content=\"{$title}\">\n"
            ."    <meta property=\"og:description\" content=\"{$desc}\">";
        if (! empty($s['og_image'])) {
            $meta .= "\n    <meta property=\"og:image\" content=\"".e(url($s['og_image'])).'">';
        }
        if (! empty($s['favicon'])) {
            $meta .= "\n    <link rel=\"icon\" href=\"".e(url($s['favicon'])).'">';
        }
        $html = preg_replace_callback('#<title>.*?</title>#s', fn () => $meta, $html, 1);
    } catch (\Throwable $e) {
        // Database not ready yet - serve the plain build.
    }

    return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
};

Route::get('/admin/login', $spa)->name('login');
Route::get('/{any?}', $spa)->where('any', '^(?!api|uploads|build|install|up$).*$');
