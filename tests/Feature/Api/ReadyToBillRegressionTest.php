<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 (#282) — S10: `ready-to-bill` is identical for historical rows.
 *
 * The billable set keeps reading the appointment projection (`status`,
 * `final_amount`, `completed_at`) and the payments, so giving every historical
 * completed appointment its closed encounter must not move a single byte of the
 * response. The test captures the payload, runs the backfill and compares.
 */
class ReadyToBillRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'is_active' => true]);
    }

    private function historicalCompleted(Patient $patient, float $amount, string $when): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'completed',
            'final_amount' => $amount,
            'completed_at' => $when,
            'consultation_mode' => 'execution',
        ]);
    }

    public function test_the_billable_set_does_not_change_after_the_backfill(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();

        // Billable: closed, with an amount and no payment at all.
        $unpaid = $this->historicalCompleted($patient, 200, now()->subDays(2)->toDateTimeString());
        // Billable: partially paid.
        $partial = $this->historicalCompleted($patient, 300, now()->subDay()->toDateTimeString());
        Transaction::factory()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $partial->id,
            'created_by' => $admin->id,
            'amount' => 100,
            'type' => 'payment',
            'status' => 'completed',
        ]);
        // Not billable: fully paid.
        $paid = $this->historicalCompleted($patient, 150, now()->subHours(3)->toDateTimeString());
        Transaction::factory()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $paid->id,
            'created_by' => $admin->id,
            'amount' => 150,
            'type' => 'payment',
            'status' => 'completed',
        ]);
        // Not billable: no amount.
        $this->historicalCompleted($patient, 0, now()->subHour()->toDateTimeString());

        $before = $this->actingAs($admin, 'sanctum')->getJson('/api/appointments/ready-to-bill');
        $before->assertOk();
        $beforeJson = $before->json();

        $this->assertSame([$partial->id, $unpaid->id], array_column($beforeJson['data'], 'id'));

        $this->artisan('encounters:backfill')->assertSuccessful();

        $after = $this->actingAs($admin, 'sanctum')->getJson('/api/appointments/ready-to-bill');
        $after->assertOk();

        $this->assertSame($beforeJson, $after->json());
        $this->assertSame(4, ClinicalEncounter::count());
    }

    public function test_the_billable_set_ignores_the_encounter_of_the_act(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();
        $appointment = $this->historicalCompleted($patient, 200, now()->subDay()->toDateTimeString());

        $this->artisan('encounters:backfill')->assertSuccessful();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();
        $encounter->forceFill(['final_amount' => 999])->save();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/appointments/ready-to-bill');

        // The billable amount is the appointment's projection, not a second pen.
        $response->assertOk()->assertJsonPath('data.0.final_amount', 200);
    }
}
