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
        'service-request' => 'Service Request',
        'cost-calculator' => 'Interior Cost Calculator',
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

        // Client portal: private pages, never indexed (not in the sitemap either).
        if ($path === 'portal' || str_starts_with($path, 'portal/')) {
            return [array_merge($page('Client Portal'), ['robots' => 'noindex,nofollow']), 200];
        }

        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            return [array_merge($meta, ['title' => 'Admin Panel | '.$siteName, 'robots' => 'noindex,nofollow']), 200];
        }

        if (array_key_exists($path, self::STATIC_PAGES)) {
            $title = self::STATIC_PAGES[$path];
            $result = $title ? $page($title) : $meta;
            // The business card for Google (Maps / local search) on the home and contact pages.
            if ($path === '' || $path === 'contact') {
                $result['schema'] = [self::business($s)];
            }
            if ($path === '') {
                $result['schema'][] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $siteName, 'url' => self::base().'/'];
            } else {
                $result['schema'][] = self::breadcrumbs([[$title, self::base().'/'.$path]]);
            }

            return [$result, 200];
        }

        $parts = explode('/', $path, 2);
        $slug = $parts[1] ?? '';
        $found = null;

        if ($slug !== '' && ! str_contains($slug, '/')) {
            // [model, default description, image, og type, section name]
            $hit = match ($parts[0]) {
                'services' => ($m = Service::active()->where('slug', $slug)->first()) ? [$m, $m->short_description ?: $m->description, $m->image, 'website', 'Services'] : null,
                'projects' => ($m = Project::active()->where('slug', $slug)->first()) ? [$m, $m->short_description ?: $m->description, $m->cover_image, 'website', 'Projects'] : null,
                'blog' => ($m = Post::published()->where('slug', $slug)->first()) ? [$m, $m->excerpt ?: $m->body, $m->image, 'article', 'Blog'] : null,
                'careers' => ($m = Career::active()->where('slug', $slug)->first()) ? [$m, $m->description, null, 'website', 'Careers'] : null,
                'page' => ($m = Page::active()->where('slug', $slug)->first()) ? [$m, $m->content, null, 'website', null] : null,
                default => null,
            };
            if ($hit) {
                [$m, $desc, $image, $type, $section] = $hit;
                $found = $page($m->title, $m->seo_description ?: $desc, $image, $type);
                if (filled($m->seo_title)) {
                    $found['title'] = $m->seo_title; // the editor's own SEO title is used as written
                }
                $url = self::base().'/'.$path;
                $found['schema'] = [self::breadcrumbs(array_filter([
                    $section ? [$section, self::base().'/'.$parts[0]] : null,
                    [$m->title, $url],
                ]))];
                if ($parts[0] === 'blog') {
                    $found['schema'][] = array_filter([
                        '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $m->title,
                        'description' => $found['description'], 'image' => self::abs($image), 'mainEntityOfPage' => $url,
                        'datePublished' => optional($m->published_at ?? $m->created_at)->toAtomString(),
                        'dateModified' => optional($m->updated_at)->toAtomString(),
                        'author' => ['@type' => 'Organization', 'name' => $siteName],
                        'publisher' => array_filter(['@type' => 'Organization', 'name' => $siteName, 'logo' => self::abs($s['logo'] ?? null) ? ['@type' => 'ImageObject', 'url' => self::abs($s['logo'])] : null]),
                    ]);
                } elseif ($parts[0] === 'services') {
                    $found['schema'][] = array_filter([
                        '@context' => 'https://schema.org', '@type' => 'Service', 'name' => $m->title, 'serviceType' => $m->title,
                        'description' => $found['description'], 'image' => self::abs($image), 'url' => $url,
                        'provider' => ['@type' => 'HomeAndConstructionBusiness', 'name' => $siteName, 'url' => self::base().'/'],
                        'areaServed' => ['@type' => 'Country', 'name' => 'Bangladesh'],
                    ]);
                }
            }
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
        if (! empty($s['google_site_verification'])) {
            $tags[] = "<meta name=\"google-site-verification\" content=\"{$e($s['google_site_verification'])}\">";
        }
        // Structured data (JSON-LD). JSON_HEX_TAG keeps "</script>" inside text from closing the tag.
        foreach ($meta['schema'] ?? [] as $data) {
            $tags[] = '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
        }

        return implode("\n    ", $tags);
    }

    /** schema.org BreadcrumbList: Home › [name, url]… */
    public static function breadcrumbs(array $items): array
    {
        $list = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => self::base().'/']];
        foreach (array_values($items) as $i => [$name, $url]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $name, 'item' => $url];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    /** The company as a local business (from Site Settings → contact, social, SEO). */
    public static function business(array $s): array
    {
        $social = array_values(array_filter(array_map(fn ($k) => filled($s[$k] ?? null) && str_starts_with((string) $s[$k], 'http') ? $s[$k] : null,
            ['facebook', 'instagram', 'youtube', 'linkedin'])));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'HomeAndConstructionBusiness',
            'name' => $s['site_name'] ?? config('app.name'),
            'description' => $s['meta_description'] ?? ($s['tagline'] ?? null),
            'url' => self::base().'/',
            'logo' => self::abs($s['logo'] ?? null),
            'image' => self::abs($s['og_image'] ?? ($s['logo'] ?? null)),
            'telephone' => $s['phone'] ?? null,
            'email' => $s['email'] ?? null,
            'address' => filled($s['address'] ?? null)
                ? ['@type' => 'PostalAddress', 'streetAddress' => trim(preg_replace('/\s+/', ' ', strip_tags((string) $s['address']))), 'addressCountry' => 'BD']
                : null,
            'areaServed' => ['@type' => 'Country', 'name' => 'Bangladesh'],
            'priceRange' => '৳৳',
            'sameAs' => $social ?: null,
        ]);
    }

    /** Official Google Tag Manager snippet for <head>. $id is already validated as GTM-XXXX. */
    public static function gtmHead(string $id): string
    {
        $id = e($id);

        return "<!-- Google Tag Manager -->\n<script>window.__gtmLoaded='{$id}';(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
            ."new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
            ."j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
            ."'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
            ."})(window,document,'script','dataLayer','{$id}');</script>\n<!-- End Google Tag Manager -->";
    }

    /** GTM <noscript> fallback for right after <body>. */
    public static function gtmBody(string $id): string
    {
        $id = e($id);

        return "<!-- Google Tag Manager (noscript) -->\n<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$id}\"\n"
            ."height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n<!-- End Google Tag Manager (noscript) -->";
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
