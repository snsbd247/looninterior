<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Client portal: staff choose which ticket updates the customer can see. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $t) {
            $t->boolean('is_public')->default(false)->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $t) {
            $t->dropColumn('is_public');
        });
    }
};
