<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Historical backfill of `oral_surgery_records.surgery_date` (issue #246).
 *
 * The one-shot data rule: an existing row dates its surgery from the linked
 * appointment (`appointments.scheduled_at`) and falls back to the day it was
 * captured when there is no appointment. A row that already carries a date is
 * never overwritten.
 *
 * The migration owns the rule in `backfill()`, which this case calls directly:
 * replaying `down()`/`up()` here would drop and re-add a column, and MySQL
 * commits that implicitly — breaking the transaction that isolates every test
 * and, as this repository already shows, the rollback of unrelated migrations.
 */
class OralSurgeryDateBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_backfill_dates_rows_from_the_appointment_or_the_capture(): void
    {
        $user = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->create([
            'scheduled_at' => '2026-05-04 15:30:00',
        ]);

        DB::table('oral_surgery_records')->insert([
            [
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'created_by' => $user->id,
                'procedure_type' => 'extraction',
                'surgery_site' => 'Sitio programado',
                'surgery_date' => null,
                'created_at' => '2026-05-01 09:00:00',
                'updated_at' => '2026-05-01 09:00:00',
            ],
            [
                'patient_id' => $patient->id,
                'appointment_id' => null,
                'created_by' => $user->id,
                'procedure_type' => 'biopsy',
                'surgery_site' => 'Sitio sin cita',
                'surgery_date' => null,
                'created_at' => '2026-02-03 11:45:00',
                'updated_at' => '2026-02-03 11:45:00',
            ],
            [
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'created_by' => $user->id,
                'procedure_type' => 'cyst_removal',
                'surgery_site' => 'Sitio ya fechado',
                'surgery_date' => '2026-01-20',
                'created_at' => '2026-05-02 08:00:00',
                'updated_at' => '2026-05-02 08:00:00',
            ],
        ]);

        $migration = include database_path(
            'migrations/2026_10_21_000100_add_surgery_date_to_oral_surgery_records_table.php'
        );
        $migration->backfill();

        $this->assertSame(
            ['2026-05-04', '2026-02-03', '2026-01-20'],
            DB::table('oral_surgery_records')->orderBy('id')->pluck('surgery_date')->all()
        );
    }
}
