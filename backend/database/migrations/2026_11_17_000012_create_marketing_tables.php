<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase G: marketing campaigns (bulk SMS / e-mail to leads and customers) and opt-out.
 * Sending runs in small batches started from the browser (no queue worker on cPanel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained('branches');
            $t->string('name');
            $t->string('channel', 10);                 // sms | email
            $t->string('subject')->nullable();         // e-mail only
            $t->text('message');
            $t->json('audience')->nullable();          // {type: leads|customers, stages:[], sources:[], …}
            $t->string('status', 15)->default('draft')->index(); // draft | sending | sent | cancelled
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('sent')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->string('recipient_type', 10);          // lead | customer
            $t->unsignedBigInteger('recipient_id');
            $t->string('name')->nullable();
            $t->string('to');                          // phone (8801…) or e-mail
            $t->string('status', 10)->default('pending')->index(); // pending | sent | failed
            $t->string('error')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'to']);
        });

        foreach (['leads', 'customers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('marketing_opt_out')->default(false);
            });
        }

        // New permissions for the existing Sales & Marketing role (Admin has "*").
        $sales = DB::table('roles')->where('slug', 'sales')->first();
        if ($sales) {
            $perms = json_decode($sales->permissions ?: '[]', true) ?: [];
            $perms = array_values(array_unique(array_merge($perms, ['marketing.view', 'marketing.send'])));
            DB::table('roles')->where('id', $sales->id)->update(['permissions' => json_encode($perms)]);
        }
    }

    public function down(): void
    {
        foreach (['leads', 'customers'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('marketing_opt_out'));
        }
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
