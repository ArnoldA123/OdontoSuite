<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up of #196/#236 for quotations: a missing id is a 404, and a real
 * failure is a 500 that logs the exception instead of echoing it to the
 * client.
 */
class QuotationErrorEnvelopeTest extends TestCase
{
    // These tests build users and resolve 404s against the real schema. Without
    // the trait the SQLite runner has no tables and the whole file dies with a
    // QueryException instead of being skipped like every other DB-backed test
    // (#170).
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    public function test_a_missing_quotation_answers_404_without_echoing_the_exception(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/quotations/999999')
            ->assertNotFound();

        $response->assertJsonPath('message', 'Presupuesto no encontrado');
        $response->assertJsonMissingPath('error');
        $this->assertStringNotContainsString('No query results', $response->getContent());
    }

    public function test_a_missing_quotation_cannot_be_approved_or_rejected(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/999999/approve')
            ->assertNotFound()
            ->assertJsonMissingPath('error');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/999999/reject', ['reason' => 'Precio alto'])
            ->assertNotFound()
            ->assertJsonMissingPath('error');
    }

    public function test_a_missing_quotation_cannot_be_updated_or_deleted(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/quotations/999999', ['notes' => 'Nota'])
            ->assertNotFound()
            ->assertJsonMissingPath('error');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/quotations/999999')
            ->assertNotFound()
            ->assertJsonMissingPath('error');
    }

    public function test_the_pdf_of_a_missing_quotation_answers_404(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/quotations/999999/pdf')
            ->assertNotFound()
            ->assertJsonPath('message', 'Presupuesto no encontrado');
    }

    public function test_a_failing_endpoint_never_echoes_the_exception(): void
    {
        $this->app->instance(QuotationService::class, new class extends QuotationService
        {
            public function summary(): array
            {
                throw new \RuntimeException('boom-secret');
            }
        });

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/quotations/summary')
            ->assertStatus(500);

        $response->assertJsonPath('message', 'Error al obtener el resumen de presupuestos');
        $response->assertJsonMissingPath('error');
        $this->assertStringNotContainsString('boom-secret', $response->getContent());
    }
}
