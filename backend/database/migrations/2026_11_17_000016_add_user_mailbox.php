<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Webmail: the staff member's own e-mail account (cPanel mailbox) for one-click Webmail login. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'mailbox')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('mailbox')->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'mailbox')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('mailbox'));
        }
    }
};
