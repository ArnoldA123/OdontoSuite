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
use App\Models\MedicalRecord;
use App\Models\DentalPiece;

class SpecialtyRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some patients and users for testing
        $patients = Patient::limit(5)->get();
        $users = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental'])->limit(3)->get();
        $medicalRecords = MedicalRecord::limit(5)->get();
        $dentalPieces = DentalPiece::limit(10)->get();

        $this->command->info('Pacientes: ' . $patients->count());
        $this->command->info('Usuarios: ' . $users->count());
        $this->command->info('Registros médicos: ' . $medicalRecords->count());
        $this->command->info('Piezas dentales: ' . $dentalPieces->count());

        if ($patients->isEmpty() || $users->isEmpty() || $medicalRecords->isEmpty() || $dentalPieces->isEmpty()) {
            $this->command->info('No hay suficientes datos para crear registros de especialidades. Ejecute primero los seeders de pacientes, usuarios y registros médicos.');
            return;
        }

        // Create Implantology Records
        foreach ($patients->take(3) as $index => $patient) {
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
                'user_id' => $users->first()->id,
                'medical_record_id' => $medicalRecords[$index]->id,
                'start_date' => now()->subMonths(rand(6, 18)),
                'end_date' => null,
                'appliance_type' => 'Brackets metálicos',
                'treatment_phase' => 'Activa',
                'initial_diagnosis' => 'Maloclusión clase II',
                'treatment_goals' => 'Corrección de la maloclusión y alineación dental',
                'progress_notes' => 'Buen progreso en la alineación. Paciente colaborador.',
                'next_adjustment_date' => now()->addDays(rand(14, 28)),
                'attachments' => [
                    'photos' => ['foto_inicial.jpg', 'foto_progreso.jpg'],
                    'xrays' => ['panoramica_inicial.jpg']
                ],
                'status' => 'active'
            ]);
        }

        // Create Endodontics Records
        foreach ($patients->take(2) as $index => $patient) {
            EndodonticsRecord::create([
                'patient_id' => $patient->id,
                'user_id' => $users->first()->id,
                'medical_record_id' => $medicalRecords[$index]->id,
                'dental_piece_id' => $dentalPieces[$index]->id,
                'diagnosis' => 'Necrosis pulpar',
                'treatment_date' => now()->subDays(rand(1, 15)),
                'anesthesia_type' => 'Lidocaína 2%',
                'working_length' => '21.5mm',
                'obturation_material' => 'Gutapercha',
                'obturation_technique' => 'Técnica de condensación lateral',
                'irrigants' => 'Hipoclorito de sodio 2.5%',
                'complications' => null,
                'notes' => 'Tratamiento exitoso. Buena obturación.',
                'status' => 'completed',
                'radiographic_data' => [
                    'pre_op' => 'Lesión periapical visible',
                    'post_op' => 'Buena obturación, sin lesiones'
                ]
            ]);
        }

        // Create Rehabilitation Records
        foreach ($patients->take(2) as $index => $patient) {
            RehabilitationRecord::create([
                'patient_id' => $patient->id,
                'user_id' => $users->first()->id,
                'medical_record_id' => $medicalRecords[$index]->id,
                'rehabilitation_type' => 'Corona',
                'dental_piece_id' => $dentalPieces[$index]->id,
                'material_used' => 'Cerámica',
                'preparation_date' => now()->subDays(rand(7, 14)),
                'placement_date' => now()->subDays(rand(1, 7)),
                'shade' => 'A2',
                'notes' => 'Corona de cerámica. Excelente resultado estético.',
                'status' => 'completed',
                'lab_details' => [
                    'lab_name' => 'Laboratorio Dental Pro',
                    'technician' => 'Juan Pérez',
                    'delivery_date' => now()->subDays(3)
                ],
                'occlusion_notes' => 'Buena oclusión. Sin interferencias.'
            ]);
        }

        // Create Oral Surgery Records
        foreach ($patients->take(2) as $index => $patient) {
            OralSurgeryRecord::create([
                'patient_id' => $patient->id,
                'user_id' => $users->first()->id,
                'medical_record_id' => $medicalRecords[$index]->id,
                'procedure_name' => 'Extracción de tercer molar',
                'dental_piece_id' => $dentalPieces[$index]->id,
                'surgery_date' => now()->subDays(rand(1, 10)),
                'anesthesia_type' => 'Lidocaína 2% con epinefrina',
                'surgical_notes' => 'Extracción exitosa. Sin complicaciones.',
                'post_operative_instructions' => 'Aplicar hielo, tomar analgésicos según indicación.',
                'complications' => null,
                'sutures_removed_date' => now()->subDays(rand(1, 5)),
                'status' => 'completed',
                'radiographic_data' => [
                    'pre_op' => 'Tercer molar impactado',
                    'post_op' => 'Extracción completa'
                ]
            ]);
        }

        $this->command->info('Registros de especialidades creados exitosamente.');
    }
}
