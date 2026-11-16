<?php

namespace App\Support;

use App\Models\Career;
use App\Models\Client;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\Inquiry;
use App\Models\JobApplication;
use App\Models\Page;
use App\Models\Post;
use App\Models\ProcessStep;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Slider;
use App\Models\Stat;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\Business\Branch;
use App\Models\Business\TaxRate;

/**
 * Definition of every admin-manageable resource.
 *
 * model     Eloquent class
 * rules     validation rules ({id} is replaced by the current record id)
 * files     image columns (uploaded to public/uploads/<resource>)
 * gallery   hasMany relation name for multi-image galleries
 * booleans  checkbox columns
 * slug      column the slug is generated from
 * search    columns searched by ?q=
 * filters   columns filterable by exact query value
 * order     default sorting
 * with      relations eager-loaded
 * readonly  records come from the website; admin may only edit `rules` fields
 * permission  role permission needed (default "website.manage"), see config/permissions.php
 * no_delete   records can only be deactivated, never deleted (business data)
 */
class ResourceRegistry
{
    public static function all(): array
    {
        $sort = 'nullable|integer|min:0|max:100000';
        $text = 'nullable|string|max:255';
        $long = 'nullable|string|max:65000';
        $html = 'nullable|string|max:4000000';

        return [
            'sliders' => [
                'model' => Slider::class,
                'files' => ['image'],
                'booleans' => ['is_active'],
                'search' => ['title'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'title' => $text,
                    'subtitle' => 'nullable|string|max:1000',
                    'button_text' => 'nullable|string|max:100',
                    'button_link' => $text,
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'services' => [
                'model' => Service::class,
                'files' => ['image'],
                'booleans' => ['is_active', 'is_featured'],
                'slug' => 'title',
                'search' => ['title'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'slug' => 'nullable|string|max:255|unique:services,slug,{id}',
                    'icon' => 'nullable|string|max:50',
                    'short_description' => 'nullable|string|max:1000',
                    'description' => $html,
                    'is_featured' => 'boolean',
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'project_categories' => [
                'model' => ProjectCategory::class,
                'booleans' => ['is_active'],
                'slug' => 'name',
                'search' => ['name'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'slug' => 'nullable|string|max:255|unique:project_categories,slug,{id}',
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'projects' => [
                'model' => Project::class,
                'files' => ['cover_image'],
                'gallery' => 'images',
                'booleans' => ['is_active', 'is_featured'],
                'slug' => 'title',
                'search' => ['title', 'client_name', 'location'],
                'filters' => ['project_category_id'],
                'with' => ['category:id,name'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'project_category_id' => 'nullable|exists:project_categories,id',
                    'title' => 'required|string|max:255',
                    'slug' => 'nullable|string|max:255|unique:projects,slug,{id}',
                    'client_name' => $text,
                    'location' => $text,
                    'area' => $text,
                    'year' => 'nullable|string|max:20',
                    'short_description' => 'nullable|string|max:1000',
                    'description' => $html,
                    'video_url' => $text,
                    'is_featured' => 'boolean',
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'testimonials' => [
                'model' => Testimonial::class,
                'files' => ['photo'],
                'booleans' => ['is_active'],
                'search' => ['name', 'designation'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'designation' => $text,
                    'message' => 'required|string|max:5000',
                    'rating' => 'nullable|integer|min:1|max:5',
                    'video_url' => $text,
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'clients' => [
                'model' => Client::class,
                'files' => ['logo'],
                'booleans' => ['is_active'],
                'search' => ['name'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'website' => $text,
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'team_members' => [
                'model' => TeamMember::class,
                'files' => ['photo'],
                'booleans' => ['is_active'],
                'search' => ['name', 'designation'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'designation' => $text,
                    'bio' => $long,
                    'facebook' => $text,
                    'linkedin' => $text,
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'stats' => [
                'model' => Stat::class,
                'booleans' => ['is_active'],
                'search' => ['label'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'label' => 'required|string|max:255',
                    'value' => 'required|integer|min:0',
                    'suffix' => 'nullable|string|max:20',
                    'icon' => 'nullable|string|max:50',
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'process_steps' => [
                'model' => ProcessStep::class,
                'booleans' => ['is_active'],
                'search' => ['title'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'description' => $long,
                    'icon' => 'nullable|string|max:50',
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'features' => [
                'model' => Feature::class,
                'booleans' => ['is_active'],
                'search' => ['title'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'description' => $long,
                    'icon' => 'nullable|string|max:50',
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'faqs' => [
                'model' => Faq::class,
                'booleans' => ['is_active'],
                'search' => ['question'],
                'order' => ['sort_order', 'asc'],
                'rules' => [
                    'question' => 'required|string|max:500',
                    'answer' => 'required|string|max:10000',
                    'sort_order' => $sort,
                    'is_active' => 'boolean',
                ],
            ],

            'posts' => [
                'model' => Post::class,
                'files' => ['image'],
                'booleans' => ['is_active'],
                'slug' => 'title',
                'search' => ['title'],
                'order' => ['published_at', 'desc'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'slug' => 'nullable|string|max:255|unique:posts,slug,{id}',
                    'excerpt' => 'nullable|string|max:1000',
                    'body' => $html,
                    'published_at' => 'nullable|date',
                    'is_active' => 'boolean',
                ],
            ],

            'pages' => [
                'model' => Page::class,
                'booleans' => ['is_active', 'show_in_footer'],
                'slug' => 'title',
                'search' => ['title'],
                'order' => ['title', 'asc'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'slug' => 'nullable|string|max:255|unique:pages,slug,{id}',
                    'content' => $html,
                    'show_in_footer' => 'boolean',
                    'is_active' => 'boolean',
                ],
            ],

            'careers' => [
                'model' => Career::class,
                'booleans' => ['is_active'],
                'slug' => 'title',
                'search' => ['title', 'location'],
                'order' => ['id', 'desc'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'slug' => 'nullable|string|max:255|unique:careers,slug,{id}',
                    'location' => $text,
                    'job_type' => $text,
                    'vacancy' => 'nullable|string|max:50',
                    'salary' => $text,
                    'deadline' => 'nullable|date',
                    'description' => $html,
                    'is_active' => 'boolean',
                ],
            ],

            'inquiries' => [
                'model' => Inquiry::class,
                'readonly' => true,
                'search' => ['name', 'phone', 'email', 'location'],
                'filters' => ['status', 'type'],
                'order' => ['id', 'desc'],
                'rules' => [
                    'status' => 'required|in:new,contacted,converted,closed',
                    'admin_note' => $long,
                ],
            ],

            'job_applications' => [
                'model' => JobApplication::class,
                'readonly' => true,
                'private_files' => ['cv_path'],
                'search' => ['name', 'phone', 'email'],
                'filters' => ['status', 'career_id'],
                'with' => ['career:id,title'],
                'order' => ['id', 'desc'],
                'rules' => [
                    'status' => 'required|in:new,shortlisted,rejected,hired',
                    'admin_note' => $long,
                ],
            ],

            /* ---------------- Business setup (simple lists) ---------------- */

            'branches' => [
                'model' => Branch::class,
                'permission' => 'branches.manage',
                'no_delete' => true,
                'booleans' => ['is_active'],
                'search' => ['name', 'code', 'phone'],
                'order' => ['id', 'asc'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'code' => 'required|alpha_num|max:10|unique:branches,code,{id}',
                    'phone' => 'nullable|string|max:50',
                    'email' => 'nullable|email|max:255',
                    'address' => 'nullable|string|max:1000',
                    'is_active' => 'boolean',
                ],
            ],

            'tax_rates' => [
                'model' => TaxRate::class,
                'permission' => 'settings.business',
                'no_delete' => true,
                'booleans' => ['is_active', 'is_default'],
                'search' => ['name'],
                'order' => ['id', 'asc'],
                'rules' => [
                    'name' => 'required|string|max:100',
                    'rate' => 'required|numeric|min:0|max:100',
                    'is_default' => 'boolean',
                    'is_active' => 'boolean',
                ],
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(static::all());
    }

    public static function get(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }
}
