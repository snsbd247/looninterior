<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional per-page SEO title/description for services, projects, blog posts and CMS pages (empty = automatic). */
return new class extends Migration
{
    private array $tables = ['services', 'projects', 'posts', 'pages'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('seo_title', 120)->nullable();
                $t->string('seo_description', 300)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['seo_title', 'seo_description']));
        }
    }
};
