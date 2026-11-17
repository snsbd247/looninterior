<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cost calculator "Room by room" mode: options with group "room" (value = Tk per room at Standard quality,
 * before the style and quality multipliers) and a starting count per room. Demo prices for the owner to replace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculator_options', function (Blueprint $t) {
            $t->unsignedTinyInteger('default_qty')->default(0); // room mode: count shown when the page opens
        });

        $now = now();
        $rooms = [
            // name, Tk per room, starting count, description, icon
            ['Living Room', 220000, 1, 'False ceiling, wall feature, TV unit, lighting', 'home'],
            ['Dining Area', 120000, 1, 'Ceiling, wall treatment, crockery cabinet', 'coffee'],
            ['Kitchen', 250000, 1, 'Cabinets, countertop, backsplash, fittings', 'box'],
            ['Master Bedroom', 190000, 1, 'Bed back wall, wardrobe, dresser, ceiling', 'star'],
            ['Bedroom', 150000, 2, 'Wardrobe, bed back wall, study table', 'heart'],
            ["Kids' Room", 140000, 0, 'Storage, study corner, playful finishes', 'smile'],
            ['Bathroom', 80000, 2, 'Vanity, mirror cabinet, fittings upgrade', 'sun'],
            ['Balcony', 40000, 0, 'Flooring, planters, lighting', 'feather'],
            ['Foyer / Entrance', 50000, 0, 'Shoe cabinet, wall panel, lighting', 'key'],
            ['Prayer / Study Room', 80000, 0, 'Shelving, desk, soft lighting', 'compass'],
        ];
        DB::table('calculator_options')->insert(array_map(fn ($r, $i) => [
            'group' => 'room', 'name' => $r[0], 'value' => $r[1], 'default_qty' => $r[2], 'description' => $r[3], 'icon' => $r[4],
            'is_default' => false, 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $rooms, array_keys($rooms)));
    }

    public function down(): void
    {
        DB::table('calculator_options')->where('group', 'room')->delete();
        Schema::table('calculator_options', fn (Blueprint $t) => $t->dropColumn('default_qty'));
    }
};
