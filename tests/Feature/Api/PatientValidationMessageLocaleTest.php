<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #403 — the field messages of a rejected create must reach the SPA in the
 * application's language.
 *
 * RED state (before the fix): the project ships no `lang/es` catalog, so
 * `POST /api/patients` answered `The document number has already been taken.`
 * and `The birth date field must be a date before today.` while the generic
 * `message` was already Spanish. The surface can only paint a reason in its
 * field if the reason arrives in the language the form speaks.
 *
 * The two rules pinned here are the ones the audit measured; `attributes`
 * translations are part of the contract because a field message that names
 * `document_number` is not the application's language either.
 */
class PatientValidationMessageLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin(), 'sanctum');
    }

    public function test_duplicate_document_number_is_reported_in_spanish(): void
    {
        Patient::create([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'document_number' => '45678912',
            'is_active' => true,
        ]);

        $response = $this->actingAsAdmin()->postJson('/api/patients', [
            'first_name' => 'Luis',
            'last_name' => 'Ramos',
            'document_number' => '45678912',
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            'El campo número de documento ya ha sido tomado.',
            $response->json('errors.document_number.0')
        );
    }

    public function test_future_birth_date_is_reported_in_spanish(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/patients', [
            'first_name' => 'Luis',
            'last_name' => 'Ramos',
            'birth_date' => '2099-01-01',
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            'La fecha de nacimiento debe ser anterior a hoy.',
            $response->json('errors.birth_date.0')
        );
    }

    public function test_empty_payload_required_messages_are_spanish(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/patients', []);

        $response->assertStatus(422);
        $this->assertSame(
            'El campo nombre es obligatorio.',
            $response->json('errors.first_name.0')
        );
        $this->assertSame(
            'El campo apellido es obligatorio.',
            $response->json('errors.last_name.0')
        );
    }
}
