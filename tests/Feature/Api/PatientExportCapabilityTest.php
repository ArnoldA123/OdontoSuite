<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * #404 — the surface knows the effective export capability, not only the
 * inert grant.
 *
 * `export.patient` is granted to `finanzas` on purpose (`PATIENT_EXPORT_ROLES`)
 * and `PatientPolicy::export()` is the effective gate. The patient payload now
 * publishes that decision, so the file no longer offers an action the policy
 * answers with 403, and the documented inert grant stays untouched.
 */
class PatientExportCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): Patient
    {
        return Patient::create([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'document_number' => '45678912',
            'is_active' => true,
        ]);
    }

    private function actingAsRole(string $role): self
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        return $this->actingAs($user, 'sanctum');
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function exportRoles(): array
    {
        return [
            'administrador' => ['administrador', true],
            'recepcionista' => ['recepcionista', true],
            'odontologo' => ['odontologo', true],
            'finanzas' => ['finanzas', false],
            'tecnico' => ['tecnico_dental', false],
        ];
    }

    #[DataProvider('exportRoles')]
    public function test_patient_payload_publishes_the_policy_decision(string $role, bool $expected): void
    {
        $patient = $this->patient();

        $response = $this->actingAsRole($role)->getJson("/api/patients/{$patient->id}");

        $response->assertOk();
        $this->assertSame($expected, $response->json('data.can_export'));
    }

    public function test_finanzas_still_receives_the_grant_and_the_policy_still_denies(): void
    {
        $patient = $this->patient();
        $finance = User::factory()->create([
            'role' => 'finanzas',
            'is_active' => true,
        ]);

        $this->assertTrue(\App\Authorization\Permissions::roleHas($finance->role, \App\Authorization\Permissions::EXPORT_PATIENT));

        $this->actingAs($finance, 'sanctum')
            ->getJson("/api/patients/{$patient->id}/export?format=pdf")
            ->assertForbidden();
    }
}
