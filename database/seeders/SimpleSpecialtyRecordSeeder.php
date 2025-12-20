<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ImplantologyRecord;
use App\Models\OrthodonticsRecord;
use App\Models\EndodonticsRecord;
use App\Models\RehabilitationRecord;
use App\Models\OralSurgeryRecord;
use App\Models\Patient;
use App\Models\User;
use App\Models\DentalPiece;

class SimpleSpecialtyRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some patients and users for testing
        $patients = Patient::limit(5)->get();
        $users = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental'])->limit(3)->get();
        $dentalPieces = DentalPiece::limit(10)->get();

        if ($patients->isEmpty() || $users->isEmpty() || $dentalPieces->isEmpty()) {
            $this->command->info('No hay suficientes datos para crear registros de especialidades.');
            return;
        }

        // Create Implantology Records
        foreach ($patients->take(2) as $index => $patient) {
            ImplantologyRecord::create([
                'patient_id' => $patient->id,
                'created_by' => $users->first()->id,
                'dental_piece_id' => $dentalPieces[$index]->id,
                'implant_brand' => 'Nobel Biocare',
                'implant_model' => 'Replace Select',
                'implant_diameter' => '4.1',
                'implant_length' => '10.0',
                'batch_number' => 'NB' . rand(1000, 9999),
                'serial_number' => 'SN' . rand(10000, 99999),
                'placement_date' => now()->subDays(rand(1, 30)),
                'healing_date' => now()->subDays(rand(1, 15)),
                'loading_date' => now()->subDays(rand(1, 7)),
                'status' => 'loaded',
                'surgical_notes' => 'Colocación exitosa del implante. Sin complicaciones.',
                'post_surgical_notes' => 'Paciente con buena cicatrización. Sin signos de infección.',
                'complications' => null,
                'radiographic_data' => [
                    'pre_op' => 'Buena densidad ósea',
                    'post_op' => 'Implante bien posicionado'
                ],
                'measurements' => [
                    'bone_height' => 12.5,
                    'bone_width' => 8.2
                ],
                'torque_value' => 35.0,
                'follow_up_notes' => 'Seguimiento programado en 3 meses.'
            ]);
        }

        // Create Orthodontics Records
        foreach ($patients->take(2) as $index => $patient) {
            OrthodonticsRecord::create([
                'patient_id' => $patient->id,
                'created_by' => $users->first()->id,
                'treatment_type' => 'brackets',
                'appliance_type' => 'Brackets metálicos',
                'treatment_start_date' => now()->subMonths(rand(6, 18)),
                'estimated_completion_date' => now()->addMonths(rand(6, 12)),
                'treatment_phase' => 'active',
                'treatment_objectives' => 'Corrección de la maloclusión y alineación dental',
                'current_notes' => 'Buen progreso en la alineación. Paciente colaborador.',
                'progress_notes' => 'Tratamiento en curso. Buena respuesta del paciente.',
                'retention_plan' => 'Retenedores fijos y removibles'
            ]);
        }

        $this->command->info('Registros de especialidades creados exitosamente.');
    }
}
