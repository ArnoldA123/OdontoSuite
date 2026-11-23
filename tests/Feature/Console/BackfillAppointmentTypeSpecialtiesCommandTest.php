<?php

namespace Tests\Feature\Console;

use App\Models\AppointmentType;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #523 — the deploy backfill for `appointment_types.specialty_id`.
 *
 * The column arrived nullable and without a migration guess: the seeder only
 * classifies the rows it creates, so an existing base stays "Sin especialidad".
 * The command applies the same explicit map, only to null rows, and reports
 * what it leaves untouched.
 */
class BackfillAppointmentTypeSpecialtiesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function specialty(string $code, string $name): Specialty
    {
        return Specialty::create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function test_it_assigns_the_mapped_specialty_to_rows_without_one(): void
    {
        $general = $this->specialty('general', 'General');
        $surgery = $this->specialty('cirugia_oral', 'Cirugía Oral');

        $consultation = AppointmentType::factory()->create([
            'name' => 'Consulta General',
            'specialty_id' => null,
        ]);
        $oralSurgery = AppointmentType::factory()->create([
            'name' => 'Cirugía Oral',
            'specialty_id' => null,
        ]);

        $this->artisan('appointment-types:backfill')
            ->expectsOutput('Tipos de cita revisados: 2')
            ->expectsOutput('Especialidades asignadas: 2')
            ->expectsOutput('Tipos sin correspondencia: 0')
            ->assertSuccessful();

        $this->assertSame($general->id, $consultation->fresh()->specialty_id);
        $this->assertSame($surgery->id, $oralSurgery->fresh()->specialty_id);
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->specialty('general', 'General');
        $type = AppointmentType::factory()->create([
            'name' => 'Consulta General',
            'specialty_id' => null,
        ]);

        $this->artisan('appointment-types:backfill')->assertSuccessful();
        $assignedAt = $type->fresh()->updated_at;

        $this->artisan('appointment-types:backfill')
            ->expectsOutput('Tipos de cita revisados: 0')
            ->expectsOutput('Especialidades asignadas: 0')
            ->assertSuccessful();

        $this->assertSame($assignedAt->toDateTimeString(), $type->fresh()->updated_at->toDateTimeString());
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $this->specialty('general', 'General');
        $type = AppointmentType::factory()->create([
            'name' => 'Consulta General',
            'specialty_id' => null,
        ]);

        $this->artisan('appointment-types:backfill', ['--dry-run' => true])
            ->expectsOutput('Especialidades asignadas: 1')
            ->expectsOutput('dry-run: no se escribió nada.')
            ->assertSuccessful();

        $this->assertNull($type->fresh()->specialty_id);
    }

    public function test_it_never_overwrites_a_human_decision_and_reports_unmatched_names(): void
    {
        $general = $this->specialty('general', 'General');
        $endodontics = $this->specialty('endodoncia', 'Endodoncia');

        $decided = AppointmentType::factory()->create([
            'name' => 'Consulta General',
            'specialty_id' => $endodontics->id,
        ]);
        $unknown = AppointmentType::factory()->create([
            'name' => 'Tipo propio de la clínica',
            'specialty_id' => null,
        ]);

        $this->artisan('appointment-types:backfill')
            ->expectsOutput('Tipos de cita revisados: 1')
            ->expectsOutput('Especialidades asignadas: 0')
            ->expectsOutput('Tipos sin correspondencia: 1')
            ->assertSuccessful();

        $this->assertSame($endodontics->id, $decided->fresh()->specialty_id);
        $this->assertNotSame($general->id, $decided->fresh()->specialty_id);
        $this->assertNull($unknown->fresh()->specialty_id);
    }
}
