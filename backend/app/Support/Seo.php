<?php

namespace App\Support;

use App\Models\Career;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Server-side SEO for the React single page app: builds the <head> tags for
 * every public URL so search engines and social networks (Facebook,
 * WhatsApp, LinkedIn...) see the right title, description and image.
 */
class Seo
{
    private const STATIC_PAGES = [
        '' => null,
        'about' => 'About Us',
        'services' => 'Our Services',
        'projects' => 'Our Projects',
        'blog' => 'Blog',
        'careers' => 'Careers',
        'contact' => 'Contact Us',
        'get-quote' => 'Get a Free Quote',
        'faq' => 'FAQ',
    ];

    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function abs(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        return self::base().'/'.ltrim($path, '/');
    }

    public static function text(?string $html, int $limit = 160): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return Str::limit($plain, $limit, '…');
    }

    /**
     * Resolve a request path ("projects/modern-flat") into page meta.
     * Returns [meta array, http status].
     */
    public static function forPath(string $path, array $s): array
    {
        $path = trim($path, '/');
        $siteName = $s['site_name'] ?? config('app.name');

        $meta = [
            'title' => $s['meta_title'] ?? $siteName,
            'description' => $s['meta_description'] ?? ($s['tagline'] ?? ''),
            'image' => $s['og_image'] ?? null,
            'type' => 'website',
            'robots' => 'index,follow',
        ];
        $status = 200;
        $page = fn (string $title, ?string $desc = null, ?string $image = null, string $type = 'website') => array_merge($meta, array_filter([
            'title' => $title.' | '.$siteName,
            'description' => $desc ? self::text($desc) : null,
            'image' => $image,
            'type' => $type,
        ]));

        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            return [array_merge($meta, ['title' => 'Admin Panel | '.$siteName, 'robots' => 'noindex,nofollow']), 200];
        }

        if (array_key_exists($path, self::STATIC_PAGES)) {
            $title = self::STATIC_PAGES[$path];

            return [$title ? $page($title) : $meta, 200];
        }

        $parts = explode('/', $path, 2);
        $slug = $parts[1] ?? '';
        $found = null;

        if ($slug !== '' && ! str_contains($slug, '/')) {
            $found = match ($parts[0]) {
                'services' => ($m = Service::active()->where('slug', $slug)->first())
                    ? $page($m->title, $m->short_description ?: $m->description, $m->image) : null,
                'projects' => ($m = Project::active()->where('slug', $slug)->first())
                    ? $page($m->title, $m->short_description ?: $m->description, $m->cover_image) : null,
                'blog' => ($m = Post::published()->where('slug', $slug)->first())
                    ? $page($m->title, $m->excerpt ?: $m->body, $m->image, 'article') : null,
                'careers' => ($m = Career::active()->where('slug', $slug)->first())
                    ? $page($m->title, $m->description) : null,
                'page' => ($m = Page::active()->where('slug', $slug)->first())
                    ? $page($m->title, $m->content) : null,
                default => null,
            };
        }

        if ($found) {
            return [$found, 200];
        }

        // Unknown URL: the React app shows its 404 page; tell crawlers too.
        return [array_merge($page('Page Not Found'), ['robots' => 'noindex,follow']), 404];
    }

    /** Render the <head> tags (replaces the build's <title>). */
    public static function tags(array $meta, string $path, array $s): string
    {
        $e = fn ($v) => e((string) $v);
        $url = self::base().'/'.ltrim(trim($path, '/'), '/');
        $url = rtrim($url, '/') ?: self::base();
        $image = self::abs($meta['image'] ?? null);

        $tags = [
            "<title>{$e($meta['title'])}</title>",
            "<meta name=\"description\" content=\"{$e($meta['description'])}\">",
            "<meta name=\"robots\" content=\"{$e($meta['robots'])}\">",
            "<link rel=\"canonical\" href=\"{$e($url)}\">",
            "<meta property=\"og:site_name\" content=\"{$e($s['site_name'] ?? config('app.name'))}\">",
            "<meta property=\"og:type\" content=\"{$e($meta['type'])}\">",
            "<meta property=\"og:title\" content=\"{$e($meta['title'])}\">",
            "<meta property=\"og:description\" content=\"{$e($meta['description'])}\">",
            "<meta property=\"og:url\" content=\"{$e($url)}\">",
            '<meta name="twitter:card" content="'.($image ? 'summary_large_image' : 'summary').'">',
        ];
        if (! empty($s['meta_keywords'])) {
            $tags[] = "<meta name=\"keywords\" content=\"{$e($s['meta_keywords'])}\">";
        }
        if ($image) {
            $tags[] = "<meta property=\"og:image\" content=\"{$e($image)}\">";
        }
        if (! empty($s['favicon'])) {
            $tags[] = "<link rel=\"icon\" href=\"{$e(self::abs($s['favicon']))}\">";
        }

        return implode("\n    ", $tags);
    }

    /** All public URLs for sitemap.xml: [loc, lastmod|null]. */
    public static function sitemapUrls(): array
    {
        $base = self::base();
        $urls = [];
        foreach (array_keys(self::STATIC_PAGES) as $p) {
            $urls[] = [$base.($p === '' ? '/' : '/'.$p), null];
        }

        $add = function (string $prefix, $items) use (&$urls, $base) {
            foreach ($items as $m) {
                $urls[] = [$base.'/'.$prefix.'/'.$m->slug, optional($m->updated_at)->toAtomString()];
            }
        };
        $add('services', Service::active()->ordered()->get(['slug', 'updated_at']));
        $add('projects', Project::active()->ordered()->get(['slug', 'updated_at']));
        $add('blog', Post::published()->orderByDesc('published_at')->get(['slug', 'updated_at']));
        $add('careers', Career::active()->orderByDesc('id')->get(['slug', 'updated_at']));
        $add('page', Page::active()->orderBy('title')->get(['slug', 'updated_at']));

        return $urls;
    }
}
