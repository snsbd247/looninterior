<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Client;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\Page;
use App\Models\Post;
use App\Models\ProcessStep;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Stat;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /** Global data needed on every page (header, footer, menus). */
    public function site()
    {
        return response()->json([
            'settings' => Setting::allAsArray(),
            'services' => Service::active()->ordered()->get(['id', 'title', 'slug']),
            'categories' => ProjectCategory::active()->ordered()->get(['id', 'name', 'slug']),
            'pages' => Page::active()->where('show_in_footer', true)->orderBy('title')->get(['id', 'title', 'slug']),
        ]);
    }

    public function home()
    {
        $services = Service::active()->where('is_featured', true)->ordered()->take(6)->get();
        if ($services->isEmpty()) {
            $services = Service::active()->ordered()->take(6)->get();
        }

        $projects = Project::with('category:id,name,slug')->active()->where('is_featured', true)
            ->orderBy('sort_order')->latest('id')->take(9)->get();
        if ($projects->isEmpty()) {
            $projects = Project::with('category:id,name,slug')->active()->latest('id')->take(9)->get();
        }

        return response()->json([
            'sliders' => Slider::active()->ordered()->get(),
            'services' => $services,
            'projects' => $projects,
            'stats' => Stat::active()->ordered()->get(),
            'process' => ProcessStep::active()->ordered()->get(),
            'features' => Feature::active()->ordered()->get(),
            'testimonials' => Testimonial::active()->ordered()->get(),
            'clients' => Client::active()->ordered()->get(),
            'faqs' => Faq::active()->ordered()->take(8)->get(),
            'posts' => Post::published()->latest('published_at')->latest('id')->take(3)->get(),
        ]);
    }

    public function about()
    {
        return response()->json([
            'team' => TeamMember::active()->ordered()->get(),
            'stats' => Stat::active()->ordered()->get(),
            'features' => Feature::active()->ordered()->get(),
            'process' => ProcessStep::active()->ordered()->get(),
            'clients' => Client::active()->ordered()->get(),
        ]);
    }

    public function services()
    {
        return response()->json([
            'data' => Service::active()->ordered()->get(),
            'process' => ProcessStep::active()->ordered()->get(),
        ]);
    }

    public function service(string $slug)
    {
        $service = Service::active()->where('slug', $slug)->firstOrFail();

        return response()->json([
            'data' => $service,
            'others' => Service::active()->whereKeyNot($service->id)->ordered()->get(['id', 'title', 'slug', 'icon']),
            'projects' => Project::with('category:id,name,slug')->active()->latest('id')->take(3)->get(),
        ]);
    }

    public function projects(Request $request)
    {
        $query = Project::with('category:id,name,slug')->active();

        if ($slug = $request->query('category')) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $slug));
        }

        $perPage = max(1, min((int) $request->query('per_page', 12), 48));

        return response()->json($query->orderBy('sort_order')->latest('id')->paginate($perPage));
    }

    public function project(string $slug)
    {
        $project = Project::with(['category:id,name,slug', 'images'])->active()->where('slug', $slug)->firstOrFail();

        $related = Project::with('category:id,name,slug')->active()
            ->whereKeyNot($project->id)
            ->when($project->project_category_id, fn ($q) => $q->where('project_category_id', $project->project_category_id))
            ->latest('id')->take(3)->get();

        return response()->json(['data' => $project, 'related' => $related]);
    }

    public function testimonials()
    {
        return response()->json(['data' => Testimonial::active()->ordered()->get()]);
    }

    public function faqs()
    {
        return response()->json(['data' => Faq::active()->ordered()->get()]);
    }

    public function posts(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 9), 48));

        return response()->json(
            Post::published()->latest('published_at')->latest('id')
                ->select(['id', 'title', 'slug', 'excerpt', 'image', 'published_at'])
                ->paginate($perPage)
        );
    }

    public function post(string $slug)
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        return response()->json([
            'data' => $post,
            'recent' => Post::published()->whereKeyNot($post->id)->latest('published_at')->take(4)
                ->get(['id', 'title', 'slug', 'image', 'published_at']),
        ]);
    }

    public function careers()
    {
        return response()->json([
            'data' => Career::active()
                ->where(fn ($q) => $q->whereNull('deadline')->orWhereDate('deadline', '>=', now()->toDateString()))
                ->latest('id')->get()->makeHidden('description'),
        ]);
    }

    public function career(string $slug)
    {
        return response()->json(['data' => Career::active()->where('slug', $slug)->firstOrFail()]);
    }

    public function page(string $slug)
    {
        return response()->json(['data' => Page::active()->where('slug', $slug)->firstOrFail()]);
    }
}
