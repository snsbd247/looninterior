<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Http\Request;
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
| SEO: sitemap.xml and robots.txt (built from APP_URL and the database).
*/
Route::get('/sitemap.xml', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach (Seo::sitemapUrls() as [$loc, $lastmod]) {
        $xml .= '  <url><loc>'.e($loc).'</loc>'.($lastmod ? '<lastmod>'.e($lastmod).'</lastmod>' : '')."</url>\n";
    }
    $xml .= '</urlset>';

    return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
});

Route::get('/robots.txt', function () {
    $body = "User-agent: *\nDisallow: /admin\nDisallow: /api\nDisallow: /portal\n\nSitemap: ".Seo::base()."/sitemap.xml\n";

    return response($body)->header('Content-Type', 'text/plain; charset=UTF-8');
});

/*
| Serve the React single page app (built into public/build) for every
| non-API URL, with per-page SEO tags injected into <head>.
*/
$spa = function (Request $request) {
    $file = public_path('build/index.html');
    if (! is_file($file)) {
        return response('Frontend build not found. Run "npm run build" inside the frontend folder.', 503);
    }

    $html = file_get_contents($file);
    $status = 200;

    try {
        $s = Setting::publicArray();
        [$meta, $status] = Seo::forPath($request->path(), $s);
        $tags = Seo::tags($meta, $request->path(), $s);
        $html = preg_replace_callback('#<title>.*?</title>#s', fn () => $tags, $html, 1);
    } catch (\Throwable $e) {
        // Database not ready yet - serve the plain build.
    }

    return response($html, $status)->header('Content-Type', 'text/html; charset=UTF-8');
};

Route::get('/admin/login', $spa)->name('login');
Route::get('/{any?}', $spa)->where('any', '^(?!api|uploads|build|install|up$).*$');
