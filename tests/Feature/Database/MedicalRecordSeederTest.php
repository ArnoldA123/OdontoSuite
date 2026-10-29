<?php

namespace Tests\Feature\Database;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Database\Seeders\MedicalRecordSeeder;
use Database\Seeders\PatientSeeder;
use Database\Seeders\RoleBasedUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Auditoría UI U11 (#374) — la base sembrada trae la muestra clínica.
 *
 * `tests/e2e/audit/u11` mide `/medical-records/:id` y necesita una historia que
 * medir. Sobre `migrate:fresh --seed` no había ninguna: el instrumento reportaba
 * `no-sample-record`, dejaba la fila de render sin evidencia y su gate cerraba
 * en rojo sin que existiera una regresión de producto.
 *
 * Las tres reglas que fija esta prueba:
 *
 *  - la base sembrada publica al menos una historia, firmada por un profesional
 *    clínico, con su número de historia;
 *  - la muestra es la cabecera, no narrativa clínica: el seeder no escribe
 *    evoluciones, porque la base sembrada se asume sin ellas y ocho aserciones
 *    de cero absoluto dependen de esa propiedad
 *    (`AiAnalysisCitationTest`, `MedicalRecordEvolutionContentTest`,
 *    `MedicalRecordSpecialtyValidationTest`, `TechnicianClinicalRecordScopeTest`
 *    y `MedicalRecordClinicalMeasurementsTest`);
 *  - repetir el seeder no duplica la muestra, así `migrate --seed` es seguro.
 */
class MedicalRecordSeederTest extends TestCase
{
    use RefreshDatabase;

    private const CLINICAL_ROLES = ['odontologo', 'implantologo'];

    private function seedSample(): void
    {
        $this->seed([RoleBasedUsersSeeder::class, PatientSeeder::class]);
        $this->seed(MedicalRecordSeeder::class);
    }

    #[Test]
    public function the_seeded_base_publishes_a_clinical_history(): void
    {
        $this->seedSample();

        $records = MedicalRecord::all();

        $this->assertGreaterThanOrEqual(2, $records->count(), 'the audit base must publish sample histories');

        foreach ($records->values() as $index => $record) {
            $this->assertNotEmpty($record->record_number, 'every sample history carries its record number');
            $this->assertSame(
                sprintf('HC-DEMO-%03d', $index + 1),
                $record->record_number,
                'the sample histories keep their correlative numbering'
            );
            $this->assertTrue($record->is_active, 'the sample history is active');

            $author = User::find($record->created_by);

            $this->assertNotNull($author, 'the sample history names its author');
            $this->assertContains(
                $author->role,
                self::CLINICAL_ROLES,
                'the sample history is signed by a clinical professional'
            );
        }
    }

    #[Test]
    public function the_sample_never_writes_clinical_evolutions(): void
    {
        $this->seedSample();

        $this->assertSame(
            0,
            ClinicalEvolution::count(),
            'the seeded base stays evolution-free: the clinical narrative belongs to real acts, not to the fixture'
        );
    }

    #[Test]
    public function re_running_the_seeder_never_duplicates_the_sample(): void
    {
        $this->seedSample();

        $records = MedicalRecord::count();

        $this->seed(MedicalRecordSeeder::class);

        $this->assertSame($records, MedicalRecord::count(), 'a repeated seed adds no history');
    }
}
