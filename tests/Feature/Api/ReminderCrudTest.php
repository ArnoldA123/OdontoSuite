<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\ReminderSchedule;
use App\Models\ReminderTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED test (slice 03, T-03.10) for ReminderController full CRUD (BF-001).
 *
 * Acceptance:
 *  - GET /api/reminders            -> 200 with {data, meta.message}
 *  - POST /api/reminders           -> 201 with {data, meta.message}
 *  - GET /api/reminders/{id}       -> 200 with {data}
 *  - PUT /api/reminders/{id}       -> 200 with {data, meta.message}
 *  - DELETE /api/reminders/{id}    -> 204
 *  - Channel whitelist restricted to [sms,email,whatsapp,push]; unknown -> 422.
 *  - Status machine enforced via ReminderSchedule::transitionTo().
 *
 * @see specs/03-stubs-501-implement.md
 */
class ReminderCrudTest extends TestCase
{
    use RefreshDatabase;

    private function odontologo(): User
    {
        return User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    private function appointmentAndTemplate(): array
    {
        $patient = Patient::factory()->create();
        $type = AppointmentType::factory()->create();
        $doctor = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => now()->addDays(2),
        ]);

        $template = ReminderTemplate::create([
            'name' => '24h',
            'type' => '24h',
            'subject' => 'Recordatorio',
            'body_html' => '<p>Hola</p>',
            'body_text' => 'Hola',
            'is_active' => true,
        ]);

        return compact('appointment', 'template', 'patient', 'doctor');
    }

    public function test_index_returns_200_envelope(): void
    {
        $user = $this->odontologo();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/reminders')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['message']]);
    }

    public function test_store_creates_reminder_and_returns_201(): void
    {
        $user = $this->odontologo();
        $ctx = $this->appointmentAndTemplate();

        $payload = [
            'appointment_id' => $ctx['appointment']->id,
            'reminder_template_id' => $ctx['template']->id,
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'channel' => 'email',
            'status' => 'pending',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/reminders', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'status', 'channel'], 'meta' => ['message']]);

        $this->assertDatabaseHas('reminder_schedules', [
            'appointment_id' => $ctx['appointment']->id,
            'channel' => 'email',
            'status' => 'pending',
        ]);
    }

    public function test_store_rejects_unknown_channel_with_422(): void
    {
        $user = $this->odontologo();
        $ctx = $this->appointmentAndTemplate();

        $payload = [
            'appointment_id' => $ctx['appointment']->id,
            'reminder_template_id' => $ctx['template']->id,
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'channel' => 'carrier-pigeon',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/reminders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_show_returns_200_with_data(): void
    {
        $user = $this->odontologo();
        $ctx = $this->appointmentAndTemplate();

        $reminder = ReminderSchedule::create([
            'appointment_id' => $ctx['appointment']->id,
            'reminder_template_id' => $ctx['template']->id,
            'scheduled_at' => now()->addDay(),
            'channel' => 'email',
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/reminders/{$reminder->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'status']]);
    }

    public function test_update_modifies_reminder(): void
    {
        $user = $this->odontologo();
        $ctx = $this->appointmentAndTemplate();

        $reminder = ReminderSchedule::create([
            'appointment_id' => $ctx['appointment']->id,
            'reminder_template_id' => $ctx['template']->id,
            'scheduled_at' => now()->addDay(),
            'channel' => 'email',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/reminders/{$reminder->id}", [
                'status' => 'queued',
                'channel' => 'sms',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.channel', 'sms');

        $this->assertDatabaseHas('reminder_schedules', [
            'id' => $reminder->id,
            'status' => 'queued',
            'channel' => 'sms',
        ]);
    }

    public function test_destroy_returns_204(): void
    {
        $user = $this->odontologo();
        $ctx = $this->appointmentAndTemplate();

        $reminder = ReminderSchedule::create([
            'appointment_id' => $ctx['appointment']->id,
            'reminder_template_id' => $ctx['template']->id,
            'scheduled_at' => now()->addDay(),
            'channel' => 'email',
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/reminders/{$reminder->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('reminder_schedules', ['id' => $reminder->id]);
    }

    public function test_show_returns_404_for_missing_reminder(): void
    {
        $user = $this->odontologo();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/reminders/999999')
            ->assertNotFound();
    }
}
