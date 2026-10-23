<?php

namespace Tests\Feature\Api;

use App\Exceptions\Money\ImmutableLedgerEntryException;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\User;
use App\Services\PatientChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ClinicalCompliancePayload;
use Tests\TestCase;

/**
 * #287 (T2, S3/S4) — the patient charge ledger.
 *
 * The closure of the act writes exactly one charge for what the patient owes,
 * and the counter has its own door for a charge that no act produced. The
 * ledger is keyed by the encounter, so the writer can be called again without
 * duplicating the debt.
 */
class PatientChargeLedgerTest extends TestCase
{
    use ClinicalCompliancePayload;
    use RefreshDatabase;

    private function professional(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function inConsultation(User $professional, array $attributes = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'user_id' => $professional->id,
            'status' => 'in_consultation',
            'checked_in_at' => now()->subMinutes(30),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            ...$this->compliancePayload(),
            'mode' => 'execution',
            'evolution' => [
                'subjective' => 'Dolor en pieza 16 desde hace tres días.',
                'objective' => 'Caries oclusal profunda, sin exposición pulpar.',
                'assessment' => 'Caries dentinaria.',
                'plan' => 'Obturación con resina compuesta.',
            ],
            'treatment_plan' => [
                'title' => 'Restauración 16',
                'items' => [
                    [
                        'procedure_name' => 'Obturación composite',
                        'dental_piece_id' => null,
                        'unit_cost' => 180,
                        'quantity' => 1,
                    ],
                ],
            ],
        ], $overrides);
    }

    private function closedEncounter(Patient $patient, array $attributes = []): ClinicalEncounter
    {
        return ClinicalEncounter::create(array_merge([
            'patient_id' => $patient->id,
            'mode' => ClinicalEncounter::MODE_EXECUTION,
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(10),
            'final_amount' => 180,
        ], $attributes));
    }

    public function test_closing_an_encounter_writes_exactly_one_charge_for_the_act(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload())
            ->assertOk();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();
        $charge = PatientCharge::where('clinical_encounter_id', $encounter->id)->sole();

        $this->assertSame(1, PatientCharge::count());
        $this->assertSame($appointment->patient_id, $charge->patient_id);
        $this->assertSame(PatientCharge::SOURCE_ENCOUNTER, $charge->source);
        $this->assertSame('180.00', $charge->amount);
        $this->assertSame('execution', $encounter->mode);
        $this->assertSame(
            $encounter->closed_at?->toDateTimeString(),
            $charge->charged_at?->toDateTimeString(),
        );
        $this->assertSame($appointment->appointmentType->name, $charge->description);
        $this->assertSame($professional->id, $charge->created_by);
    }

    public function test_calling_the_writer_twice_keeps_one_charge(): void
    {
        $patient = Patient::factory()->create();
        $encounter = $this->closedEncounter($patient);
        $service = app(PatientChargeService::class);

        $first = $service->chargeForEncounter($encounter);
        $second = $service->chargeForEncounter($encounter->fresh());

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PatientCharge::where('clinical_encounter_id', $encounter->id)->count());
    }

    public function test_an_act_without_amount_writes_no_charge(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional, [
            'appointment_type_id' => AppointmentType::factory()->create(['price' => 0]),
        ]);

        $payload = $this->payload(['mode' => 'consultation']);
        unset($payload['treatment_plan']);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $payload)
            ->assertOk();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();

        $this->assertSame('0.00', $encounter->final_amount);
        $this->assertSame(0, PatientCharge::count());
        $this->assertNull(app(PatientChargeService::class)->chargeForEncounter($encounter));
    }

    public function test_an_act_without_an_appointment_names_its_charge_from_the_mode(): void
    {
        $patient = Patient::factory()->create();
        $encounter = $this->closedEncounter($patient, ['mode' => ClinicalEncounter::MODE_URGENCY]);

        $charge = app(PatientChargeService::class)->chargeForEncounter($encounter);

        $this->assertNotNull($charge);
        $this->assertSame('Urgencia', $charge->description);
    }

    public function test_the_counter_records_a_manual_charge(): void
    {
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/patient-charges', [
                'patient_id' => $patient->id,
                'amount' => 250,
                'description' => 'Anticipo de ortodoncia',
                'notes' => 'Acordado con la familia.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.source', PatientCharge::SOURCE_MANUAL)
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.description', 'Anticipo de ortodoncia')
            ->assertJsonPath('meta.message', 'Cargo registrado exitosamente');

        $charge = PatientCharge::sole();
        $this->assertNull($charge->clinical_encounter_id);
        $this->assertSame($receptionist->id, $charge->created_by);
        $this->assertSame(now()->toDateString(), $charge->charged_at?->toDateString());
    }

    public function test_the_counter_can_record_more_than_one_manual_charge_for_a_patient(): void
    {
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
        $patient = Patient::factory()->create();

        foreach (['Anticipo de ortodoncia', 'Radiografía panorámica'] as $description) {
            $this->actingAs($receptionist, 'sanctum')
                ->postJson('/api/patient-charges', [
                    'patient_id' => $patient->id,
                    'amount' => 100,
                    'description' => $description,
                ])
                ->assertCreated();
        }

        $this->assertSame(2, PatientCharge::where('patient_id', $patient->id)->count());
    }

    public function test_a_clinical_role_cannot_record_a_manual_charge(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/patient-charges', [
                'patient_id' => $patient->id,
                'amount' => 250,
                'description' => 'Anticipo de ortodoncia',
            ])
            ->assertForbidden();

        $this->assertSame(0, PatientCharge::count());
    }

    public function test_a_manual_charge_rejects_a_zero_amount(): void
    {
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
        $patient = Patient::factory()->create();

        $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/patient-charges', [
                'patient_id' => $patient->id,
                'amount' => 0,
                'description' => 'Anticipo de ortodoncia',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame(0, PatientCharge::count());
    }

    public function test_a_manual_charge_rejects_a_future_date(): void
    {
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
        $patient = Patient::factory()->create();

        $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/patient-charges', [
                'patient_id' => $patient->id,
                'amount' => 250,
                'description' => 'Anticipo de ortodoncia',
                'charged_at' => now()->addDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('charged_at');

        $this->assertSame(0, PatientCharge::count());
    }

    public function test_a_manual_charge_rejects_an_inactive_patient(): void
    {
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
        $patient = Patient::factory()->create(['is_active' => false]);

        $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/patient-charges', [
                'patient_id' => $patient->id,
                'amount' => 250,
                'description' => 'Anticipo de ortodoncia',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patient_id');

        $this->assertSame(0, PatientCharge::count());
    }

    public function test_a_charge_cannot_be_edited(): void
    {
        $charge = $this->manualCharge();

        $this->expectException(ImmutableLedgerEntryException::class);

        $charge->update(['amount' => 1]);
    }

    public function test_a_charge_cannot_be_deleted(): void
    {
        $charge = $this->manualCharge();

        $this->expectException(ImmutableLedgerEntryException::class);

        $charge->delete();
    }

    private function manualCharge(): PatientCharge
    {
        return PatientCharge::create([
            'patient_id' => Patient::factory()->create()->id,
            'source' => PatientCharge::SOURCE_MANUAL,
            'description' => 'Cargo de prueba',
            'amount' => 100,
            'charged_at' => now(),
        ]);
    }
}
