<?php

namespace Tests\Feature\Modules\Concerns;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\ReminderSchedule;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Fixtures shared by the reminder Feature tests (issue #154).
 *
 * Time is frozen so the 24h/48h/72h windows and their exact boundaries are
 * deterministic instead of racing the wall clock.
 */
trait BuildsReminderFixtures
{
    private Carbon $reminderNow;

    /** @var array<string, ReminderTemplate> */
    private array $reminderTemplates = [];

    private function freezeReminderClock(): void
    {
        $this->reminderNow = Carbon::parse('2026-11-02 09:00:00');
        $this->travelTo($this->reminderNow);
    }

    private function activeTemplates(string ...$types): void
    {
        foreach ($types as $type) {
            $this->reminderTemplates[$type] = ReminderTemplate::create([
                'name' => "Template {$type}",
                'type' => $type,
                'subject' => 'Recordatorio',
                'body_html' => '<p>Recordatorio</p>',
                'body_text' => 'Recordatorio',
                'is_active' => true,
            ]);
        }
    }

    private function type(bool $requiresConfirmation = true): AppointmentType
    {
        return AppointmentType::factory()->create([
            'requires_confirmation' => $requiresConfirmation,
            'is_active' => true,
        ]);
    }

    /**
     * Book through AppointmentService so the production create path — the one
     * under test — is the one that runs.
     */
    private function book(Carbon $scheduledAt, ?AppointmentType $type = null): Appointment
    {
        $doctor = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
        $this->actingAs($doctor, 'sanctum');

        return app(AppointmentService::class)->createAppointment([
            'patient_id' => Patient::factory()->create(['is_active' => true])->id,
            'user_id' => $doctor->id,
            'dental_chair_id' => DentalChair::factory()->create()->id,
            'appointment_type_id' => ($type ?? $this->type())->id,
            // Naive app-timezone string (the format the calendar sends): an ISO
            // string with Z is stored shifted by the app offset in the
            // pre-existing create path, which is out of this issue's scope.
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
        ]);
    }

    /**
     * @return Collection<int, ReminderSchedule>
     */
    private function remindersOf(Appointment $appointment): Collection
    {
        return ReminderSchedule::where('appointment_id', $appointment->id)
            ->orderBy('hours_before')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function pendingHours(Appointment $appointment): array
    {
        return $this->remindersOf($appointment)
            ->where('status', 'pending')
            ->pluck('hours_before')
            ->map(fn ($hours) => (int) $hours)
            ->values()
            ->all();
    }
}
