<?php

use App\Support\BusinessFoundation;
use Illuminate\Database\Migrations\Migration;

/* A "Main Store" per branch (idempotent). */
return new class extends Migration
{
    public function up(): void
    {
        BusinessFoundation::ensure();
    }

    public function down(): void
    {
    }
};
