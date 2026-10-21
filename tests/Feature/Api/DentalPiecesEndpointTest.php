<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
