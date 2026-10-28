<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #412 / T4 (S11, S12) — the patient search endpoint must find a patient by
 * full name written as two words, in either order, without losing the
 * document, email and phone lookups, and must publish the `name` key the
 * cash-register modal renders.
 */
class PatientSearchFullNameTest extends TestCase
{
    use RefreshDatabase;

    private ?User $actor = null;

    private function actor(): User
    {
        return $this->actor ??= User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function seedPatient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'first_name' => 'Gonzalo',
            'last_name' => 'Velasco',
            'document_number' => '45678912',
            'email' => 'gonzalo.velasco@example.com',
            'phone' => '987654321',
            'birth_date' => '1990-04-15',
            'gender' => 'male',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function search(string $term): array
    {
        $response = $this->actingAs($this->actor(), 'sanctum')
            ->getJson('/api/patients/search?'.http_build_query(['q' => $term]));

        $response->assertOk();

        return $response->json('data');
    }

    public function test_full_name_query_finds_the_patient_and_publishes_the_name_key(): void
    {
        $patient = $this->seedPatient();

        $rows = $this->search('Gonzalo Velasco');

        $row = collect($rows)->firstWhere('id', $patient->id);

        $this->assertNotNull($row, 'q=Gonzalo Velasco must find Gonzalo Velasco.');
        $this->assertSame('Gonzalo Velasco', $row['name']);
        $this->assertSame('Gonzalo', $row['first_name']);
        $this->assertSame('Velasco', $row['last_name']);
    }

    public function test_reversed_word_order_also_finds_the_patient(): void
    {
        $patient = $this->seedPatient();

        $rows = $this->search('Velasco Gonzalo');

        $this->assertNotNull(
            collect($rows)->firstWhere('id', $patient->id),
            'q=Velasco Gonzalo must find Gonzalo Velasco.'
        );
    }

    public function test_single_token_query_still_finds_the_patient(): void
    {
        $patient = $this->seedPatient();

        $rows = $this->search('Gonzalo');

        $this->assertNotNull(
            collect($rows)->firstWhere('id', $patient->id),
            'q=Gonzalo must still find Gonzalo Velasco.'
        );
    }

    public function test_document_email_and_phone_queries_still_find_the_patient(): void
    {
        $patient = $this->seedPatient();

        foreach (['45678912', 'gonzalo.velasco@example.com', '987654321'] as $term) {
            $rows = $this->search($term);

            $this->assertNotNull(
                collect($rows)->firstWhere('id', $patient->id),
                'q='.$term.' must still find Gonzalo Velasco.'
            );
        }
    }

    public function test_query_without_matches_returns_an_empty_data_array(): void
    {
        $this->seedPatient();

        $this->assertSame([], $this->search('Nadie Coincide'));
    }

    public function test_inactive_patient_is_not_returned(): void
    {
        $inactive = $this->seedPatient([
            'first_name' => 'Inactivo',
            'last_name' => 'Velasco',
            'document_number' => '12345678',
            'email' => 'inactivo.velasco@example.com',
            'phone' => '900000001',
            'is_active' => false,
        ]);

        $rows = $this->search('Inactivo Velasco');

        $this->assertNull(collect($rows)->firstWhere('id', $inactive->id));
    }
}
