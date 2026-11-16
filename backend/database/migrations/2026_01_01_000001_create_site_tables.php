<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->longText('value')->nullable();
            $t->timestamps();
        });

        Schema::create('sliders', function (Blueprint $t) {
            $t->id();
            $t->string('title')->nullable();
            $t->text('subtitle')->nullable();
            $t->string('image')->nullable();
            $t->string('button_text')->nullable();
            $t->string('button_link')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('icon', 50)->nullable();
            $t->text('short_description')->nullable();
            $t->longText('description')->nullable();
            $t->string('image')->nullable();
            $t->boolean('is_featured')->default(false);
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('project_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_category_id')->nullable()->constrained('project_categories')->nullOnDelete();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('client_name')->nullable();
            $t->string('location')->nullable();
            $t->string('area')->nullable();
            $t->string('year', 20)->nullable();
            $t->text('short_description')->nullable();
            $t->longText('description')->nullable();
            $t->string('cover_image')->nullable();
            $t->string('video_url')->nullable();
            $t->boolean('is_featured')->default(false);
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('project_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->string('image');
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('designation')->nullable();
            $t->string('photo')->nullable();
            $t->text('message');
            $t->unsignedTinyInteger('rating')->default(5);
            $t->string('video_url')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('logo')->nullable();
            $t->string('website')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('team_members', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('designation')->nullable();
            $t->string('photo')->nullable();
            $t->text('bio')->nullable();
            $t->string('facebook')->nullable();
            $t->string('linkedin')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('stats', function (Blueprint $t) {
            $t->id();
            $t->string('label');
            $t->unsignedInteger('value')->default(0);
            $t->string('suffix', 20)->nullable();
            $t->string('icon', 50)->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        foreach (['process_steps', 'features'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id();
                $t->string('title');
                $t->text('description')->nullable();
                $t->string('icon', 50)->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        Schema::create('faqs', function (Blueprint $t) {
            $t->id();
            $t->string('question', 500);
            $t->text('answer');
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->text('excerpt')->nullable();
            $t->longText('body')->nullable();
            $t->string('image')->nullable();
            $t->date('published_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->longText('content')->nullable();
            $t->boolean('show_in_footer')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('careers', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('location')->nullable();
            $t->string('job_type')->nullable();
            $t->string('vacancy', 50)->nullable();
            $t->string('salary')->nullable();
            $t->date('deadline')->nullable();
            $t->longText('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('career_id')->nullable()->constrained('careers')->nullOnDelete();
            $t->string('name');
            $t->string('email');
            $t->string('phone', 30);
            $t->string('cv_path')->nullable();
            $t->text('cover_letter')->nullable();
            $t->string('status', 20)->default('new');
            $t->text('admin_note')->nullable();
            $t->timestamps();
        });

        Schema::create('inquiries', function (Blueprint $t) {
            $t->id();
            $t->string('type', 20)->default('quote');
            $t->string('name');
            $t->string('phone', 30);
            $t->string('email')->nullable();
            $t->string('service')->nullable();
            $t->string('property_type')->nullable();
            $t->string('area_size')->nullable();
            $t->string('budget')->nullable();
            $t->string('location')->nullable();
            $t->text('message')->nullable();
            $t->string('status', 20)->default('new');
            $t->text('admin_note')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'inquiries', 'job_applications', 'careers', 'pages', 'posts', 'faqs', 'features',
            'process_steps', 'stats', 'team_members', 'clients', 'testimonials', 'project_images',
            'projects', 'project_categories', 'services', 'sliders', 'settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
