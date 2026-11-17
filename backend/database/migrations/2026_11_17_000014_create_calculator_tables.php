<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Website "Cost Calculator" (/cost-calculator): options editable in Admin → Content → Cost Calculator.
 * group: type (value = Tk per sqft) | style (value = multiplier) | quality (value = multiplier) | addon (value = fixed Tk).
 * The demo values below are placeholders for the owner to replace with real rates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculator_options', function (Blueprint $t) {
            $t->id();
            $t->string('group', 10)->index();
            $t->string('name');
            $t->string('description')->nullable();
            $t->string('icon', 30)->nullable();
            $t->decimal('value', 14, 4)->default(0);
            $t->boolean('is_default')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // The estimate a visitor asked for (selections + server-computed figures), for the PDF and the lead note.
        Schema::table('inquiries', function (Blueprint $t) {
            $t->json('details')->nullable();
        });

        $now = now();
        $rows = [];
        $add = function (string $group, array $items) use (&$rows, $now) {
            foreach ($items as $i => [$name, $value, $description, $icon]) {
                $rows[] = ['group' => $group, 'name' => $name, 'value' => $value, 'description' => $description, 'icon' => $icon,
                    'is_default' => $i === 0, 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        };
        $add('type', [
            ['Apartment', 1400, 'Flats of any size', 'home'],
            ['Duplex', 1700, 'Two-storey homes', 'layers'],
            ['Villa / House', 2300, 'Independent houses', 'award'],
            ['Office', 1800, 'Corporate & small offices', 'briefcase'],
            ['Restaurant / Café', 2100, 'Food & beverage spaces', 'coffee'],
            ['Showroom / Retail', 1700, 'Shops and showrooms', 'grid'],
        ]);
        $add('style', [
            ['Modern', 1.00, 'Clean lines, practical', 'feather'],
            ['Minimalist', 0.95, 'Less is more', 'compass'],
            ['Contemporary', 1.10, 'Current trends, warm finishes', 'sun'],
            ['Scandinavian', 1.10, 'Light wood, soft colours', 'heart'],
            ['Industrial', 1.10, 'Raw textures, metal accents', 'tool'],
            ['Classic', 1.25, 'Mouldings and rich detail', 'star'],
            ['Luxury', 1.45, 'High-end statement design', 'award'],
        ]);
        $add('quality', [
            ['Standard', 1.00, 'Local board, laminate, standard fittings', 'check'],
            ['Premium', 1.35, 'Imported board, veneer, branded fittings', 'thumbs'],
            ['Luxury', 1.90, 'Solid wood, stone, designer fittings', 'award'],
        ]);
        $add('addon', [
            ['3D Design & Walkthrough', 25000, 'Photo-realistic 3D views before work starts', 'monitor'],
            ['Custom Furniture Package', 150000, 'Made-to-measure sofa, bed, wardrobe', 'box'],
            ['Smart Home Lighting & Control', 80000, 'App-controlled lights, curtains and AC', 'zap'],
            ['Kitchen Upgrade', 120000, 'Premium cabinets, hardware and countertop', 'coffee'],
            ['Extended Warranty', 30000, 'Longer warranty on workmanship', 'shield'],
        ]);
        foreach ($rows as &$r) {
            $r['is_default'] = $r['is_default'] && $r['group'] !== 'addon';
        }
        DB::table('calculator_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::table('inquiries', fn (Blueprint $t) => $t->dropColumn('details'));
        Schema::dropIfExists('calculator_options');
    }
};
