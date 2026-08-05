<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use Tests\TestCase;

/**
 * Slice 04 / T-04.7 + T-04.9 — Appointment $fillable cleanup.
 *
 * Asserts the 5 non-existent columns listed in BF-011 are removed from
 * Appointment::$fillable (and the matching `$casts` entries that depended
 * on them):
 *   - specialty
 *   - requires_anesthesia
 *   - treatment_plan_item_id
 *   - origin_appointment_id
 *   - last_activity_at
 *
 * RED: the columns ARE in $fillable today, so each assertion fails.
 * GREEN: after slice 04 cleanup, the columns are gone and mass-assignment
 * silently drops them — which is the documented intent (audit BF-011).
 *
 * This is a pure unit-style test (no DB writes) so it is not subject to the
 * pre-existing SQLite MODIFY COLUMN tech debt documented in AGENTS.md §6.
 */
class AppointmentFillableTest extends TestCase
{
    /**
     * Columns that must NOT be mass-assignable on Appointment.
     */
    public const REMOVED_COLUMNS = [
        'specialty',
        'requires_anesthesia',
        'treatment_plan_item_id',
        'origin_appointment_id',
        'last_activity_at',
    ];

    public function test_fillable_does_not_contain_removed_columns(): void
    {
        $fillable = (new Appointment())->getFillable();

        foreach (self::REMOVED_COLUMNS as $column) {
            $this->assertNotContains(
                $column,
                $fillable,
                "Appointment::\$fillable must not contain non-existent column '{$column}' (BF-011).",
            );
        }
    }

    public function test_casts_do_not_contain_last_activity_at(): void
    {
        $casts = (new Appointment())->getCasts();

        $this->assertArrayNotHasKey(
            'last_activity_at',
            $casts,
            "Appointment::\$casts must not reference non-existent column 'last_activity_at'.",
        );
    }

    public function test_casts_do_not_contain_requires_anesthesia(): void
    {
        $casts = (new Appointment())->getCasts();

        $this->assertArrayNotHasKey(
            'requires_anesthesia',
            $casts,
            "Appointment::\$casts must not reference non-existent column 'requires_anesthesia'.",
        );
    }

    public function test_mass_assignment_with_removed_keys_is_silently_dropped(): void
    {
        // Mass-assign with the removed keys plus a real column. The real
        // column must persist; the removed keys must be silently dropped
        // (Laravel's default mass-assignment behavior).
        $appointment = new Appointment();
        $appointment->fill([
            'patient_id' => 42,
            'specialty' => 'should-be-dropped',
            'requires_anesthesia' => true,
            'treatment_plan_item_id' => 99,
            'origin_appointment_id' => 100,
            'last_activity_at' => '2026-08-05 12:00:00',
        ]);

        $this->assertSame(42, $appointment->patient_id);
        $this->assertNull($appointment->specialty);
        $this->assertNull($appointment->requires_anesthesia);
        $this->assertNull($appointment->treatment_plan_item_id);
        $this->assertNull($appointment->origin_appointment_id);
        $this->assertNull($appointment->last_activity_at);
    }
}
