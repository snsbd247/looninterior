<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\JobApplication;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'counts' => [
                'projects' => Project::count(),
                'services' => Service::count(),
                'inquiries' => Inquiry::count(),
                'new_inquiries' => Inquiry::where('status', 'new')->count(),
                'applications' => JobApplication::count(),
                'new_applications' => JobApplication::where('status', 'new')->count(),
                'posts' => Post::count(),
                'testimonials' => Testimonial::count(),
                'team' => TeamMember::count(),
            ],
            'recent_inquiries' => Inquiry::latest('id')->take(8)->get(),
            'recent_applications' => JobApplication::with('career:id,title')->latest('id')->take(5)->get(),
        ]);
    }

    public function cv(int $id)
    {
        $application = JobApplication::findOrFail($id);
        $path = $application->getAttributes()['cv_path'] ?? null;

        abort_if(! $path || str_contains($path, '..'), 404);
        $full = storage_path('app/'.$path);
        abort_unless(is_file($full), 404);

        $name = Str::slug($application->name ?: 'applicant').'-cv.'.pathinfo($full, PATHINFO_EXTENSION);

        return response()->download($full, $name);
    }
}
