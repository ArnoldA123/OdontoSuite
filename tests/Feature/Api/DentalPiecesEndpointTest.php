<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\ToothSurface;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Database\Seeders\ToothSurfaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Read contract for the FDI dental-piece catalog (issue #212).
 *
 * The specialty-record form needs the piece ids, and three of the five tables
 * declare `dental_piece_id` as NOT NULL. The endpoint is read-only, ordered by
 * FDI notation and restricted to the same roles that write specialty records.
 */
class DentalPiecesEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_it_lists_the_active_pieces_in_fdi_order_with_the_fields_the_form_renders(): void
    {
        $this->seed(DentalPieceSeeder::class);

        $response = $this->actingAs($this->user('odontologo'), 'sanctum')
            ->getJson('/api/dental-pieces');

        $response->assertOk();

        $pieces = $response->json('data');

        $this->assertCount(32, $pieces);
        $this->assertSame('11', $pieces[0]['fdi_number']);
        $this->assertSame('48', $pieces[31]['fdi_number']);
        $this->assertNotEmpty($pieces[0]['name']);

        $inactive = DentalPiece::query()->where('fdi_number', '18')->firstOrFail();
        $inactive->forceFill(['is_active' => false])->save();

        $this->actingAs($this->user('odontologo'), 'sanctum')
            ->getJson('/api/dental-pieces')
            ->assertOk()
            ->assertJsonCount(31, 'data');
    }

    public function test_roles_outside_the_clinical_scope_cannot_read_the_catalog(): void
    {
        $this->seed(DentalPieceSeeder::class);

        $this->actingAs($this->user('recepcionista'), 'sanctum')
            ->getJson('/api/dental-pieces')
            ->assertForbidden();
    }

    /**
     * #293 S1/S2: a surface procedure names its surface, so the form needs the
     * five canonical surfaces of every piece it offers.
     */
    public function test_it_answers_every_active_piece_with_its_five_surfaces(): void
    {
        $this->seed(DentalPieceSeeder::class);
        $this->seed(ToothSurfaceSeeder::class);

        $this->actingAs($this->user('odontologo'), 'sanctum');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/dental-pieces');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        $pieces = $response->json('data');
        $this->assertCount(32, $pieces);

        foreach ($pieces as $piece) {
            $this->assertCount(5, $piece['surfaces'], "la pieza {$piece['fdi_number']} debe traer sus cinco superficies");
            $this->assertSame(
                ['D', 'L', 'M', 'O', 'V'],
                array_column($piece['surfaces'], 'surface_code')
            );
        }

        $occlusal = $pieces[0]['surfaces'][3];
        $this->assertSame('O', $occlusal['surface_code']);
        $this->assertSame('Oclusal', $occlusal['surface_name']);
        $this->assertSame('O', $occlusal['abbreviation']);
        $this->assertIsInt($occlusal['id']);

        // Eager loaded: one query for the surface table, never one per piece.
        $surfaceQueries = array_filter(
            $queries,
            fn (array $query) => str_contains($query['query'], 'tooth_surfaces')
        );
        $this->assertCount(1, $surfaceQueries);
    }

    /**
     * #293: `tooth_surfaces` had no seeder at all, so a surface procedure could
     * never satisfy its rule. Re-running the seeder must not duplicate rows.
     */
    public function test_the_surface_seeder_leaves_five_surfaces_per_piece_when_run_twice(): void
    {
        $this->seed(DentalPieceSeeder::class);
        $this->seed(ToothSurfaceSeeder::class);
        $this->seed(ToothSurfaceSeeder::class);

        $this->assertSame(32 * 5, ToothSurface::query()->count());

        $sobres = ToothSurface::query()
            ->selectRaw('dental_piece_id, count(*) as total')
            ->groupBy('dental_piece_id')
            ->havingRaw('count(*) <> 5')
            ->get();

        $this->assertCount(0, $sobres, 'cada pieza debe conservar exactamente cinco superficies');

        $this->assertSame(
            ['D', 'L', 'M', 'O', 'V'],
            ToothSurface::query()
                ->where('dental_piece_id', DentalPiece::query()->where('fdi_number', '16')->value('id'))
                ->orderBy('surface_code')
                ->pluck('surface_code')
                ->all()
        );
    }
}
