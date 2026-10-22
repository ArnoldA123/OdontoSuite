<?php

namespace Database\Seeders;

use App\Models\DentalPiece;
use App\Models\ToothSurface;
use Illuminate\Database\Seeder;

/**
 * #293 S1/S2.
 *
 * `tooth_surfaces` had no seeder at all, so a procedure that applies to a
 * surface could never satisfy its rule and the forms had no surface to offer.
 * Each active FDI piece gets the five canonical surfaces keyed by
 * (`dental_piece_id`, `surface_code`), the unique pair the table declares.
 *
 * Idempotent: `firstOrCreate` matches by that pair, so a second run creates
 * zero rows and never duplicates a surface.
 */
class ToothSurfaceSeeder extends Seeder
{
    /**
     * @var array<int, array{surface_code: string, surface_name: string}>
     */
    public const SURFACES = [
        ['surface_code' => 'V', 'surface_name' => 'Vestibular'],
        ['surface_code' => 'L', 'surface_name' => 'Lingual'],
        ['surface_code' => 'M', 'surface_name' => 'Mesial'],
        ['surface_code' => 'D', 'surface_name' => 'Distal'],
        ['surface_code' => 'O', 'surface_name' => 'Oclusal'],
    ];

    public function run(): void
    {
        $pieces = DentalPiece::query()->where('is_active', true)->get(['id']);

        $created = 0;
        foreach ($pieces as $piece) {
            foreach (self::SURFACES as $surface) {
                $row = ToothSurface::firstOrCreate(
                    [
                        'dental_piece_id' => $piece->id,
                        'surface_code' => $surface['surface_code'],
                    ],
                    [
                        'surface_name' => $surface['surface_name'],
                        'abbreviation' => $surface['surface_code'],
                        'is_active' => true,
                    ]
                );

                if ($row->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $expected = $pieces->count() * count(self::SURFACES);

        $this->command->info("Superficies dentales: {$created} nuevas, ".($expected - $created).' ya existentes.');
    }
}
