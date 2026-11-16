<?php

use App\Support\BusinessFoundation;
use Illuminate\Database\Migrations\Migration;

/*
| Creates the default roles, a "Head Office" branch, a default VAT rate and gives
| existing admin users the Admin role. Idempotent (see App\Support\BusinessFoundation).
| Runs as a migration so that existing sites (cPanel /install) get it automatically.
*/
return new class extends Migration
{
    public function up(): void
    {
        BusinessFoundation::ensure();
    }

    public function down(): void
    {
        // Data only: nothing to undo (tables are dropped by the previous migration).
    }
};
