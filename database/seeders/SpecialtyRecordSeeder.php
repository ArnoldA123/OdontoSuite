<?php

namespace Database\Seeders;

use App\Models\DentalPiece;
use App\Models\EndodonticsRecord;
use App\Models\ImplantologyRecord;
use App\Models\OralSurgeryRecord;
use App\Models\OrthodonticsRecord;
use App\Models\Patient;
use App\Models\RehabilitationRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * full-user-browser-audit-2026-08-05 / PR4 / Phase 3b — GREEN rewrite.
 *
 * Re-aligned against each model's live `$fillable` array and the live
 * migration enum/string columns:
 *
 *   - OrthodonticsRecord::treatment_phase ∈ {initial, active, retention, completed}
 *   - EndodonticsRecord::treatment_status ∈ {in_progress, completed, failed, retreatment}
 *   - RehabilitationRecord::status ∈ {impression, laboratory, try_in, delivered, cemented, failed}
 *   - OralSurgeryRecord::status ∈ {scheduled, in_progress, completed, cancelled, complications}
 *   - ImplantologyRecord::status ∈ {placed, healing, loaded, failed, removed}
 *
 * FK chain (must run after DentalPieceSeeder, RoleBasedUsersSeeder, PatientSeeder):
 *   patient_id  → patients
 *   created_by  → users (replaces legacy `user_id`)
 *   dental_piece_id → dental_pieces (nullable only on OralSurgeryRecord)
 *
 * Guarded by `tests/Unit/Seeders/SpecialtyRecordSeederFieldContractTest.php`
 * (parser-based contract test that locks `keys ⊆ $fillable` per Model::create).
 */
class SpecialtyRecordSeeder extends Seeder
{
    public function run(): void
    {
        $patients = Patient::limit(5)->get();
        $users = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental'])->limit(3)->get();
        $dentalPieces = DentalPiece::limit(10)->get();

        $this->command->info('Pacientes: ' . $patients->count());
        $this->command->info('Usuarios: ' . $users->count());
        $this->command->info('Piezas dentales: ' . $dentalPieces->count());

        if ($patients->isEmpty() || $users->isEmpty() || $dentalPieces->isEmpty()) {
            $this->command->info('No hay suficientes datos para crear registros de especialidades. Ejecute primero los seeders de pacientes, usuarios y piezas dentales.');
            return;
        }

        $creatorId = $users->first()->id;
        $pieceIds = $dentalPieces->pluck('id')->all();

        // -------------------------------------------------------------------
        // ImplantologyRecord — already aligned pre-PR4; kept for regression
        // -------------------------------------------------------------------
        foreach ($patients->take(3)->values() as $index => $patient) {
            ImplantologyRecord::create([
                'patient_id' => $patient->id,
                'created_by' => $creatorId,
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
                    'post_op' => 'Implante bien posicionado',
                ],
                'measurements' => [
                    'bone_height' => 12.5,
                    'bone_width' => 8.2,
                ],
                'torque_value' => 35.0,
                'follow_up_notes' => 'Seguimiento programado en 3 meses.',
            ]);
        }

        // -------------------------------------------------------------------
        // OrthodonticsRecord — fillable keys + enum-safe treatment_phase
        // -------------------------------------------------------------------
        $orthodonticPhases = ['initial', 'active', 'retention', 'completed'];
        $orthodonticTypes = ['brackets', 'aligners', 'functional', 'fixed'];
        foreach ($patients->take(2)->values() as $index => $patient) {
            OrthodonticsRecord::create([
                'patient_id' => $patient->id,
                'created_by' => $creatorId,
                'treatment_type' => $orthodonticTypes[$index % count($orthodonticTypes)],
                'appliance_type' => 'Brackets metálicos',
                'treatment_start_date' => now()->subMonths(rand(6, 18))->toDateString(),
                'estimated_completion_date' => now()->addMonths(rand(6, 12))->toDateString(),
                'treatment_phase' => $orthodonticPhases[$index % count($orthodonticPhases)],
                'treatment_objectives' => 'Corrección de la maloclusión y alineación dental',
                'current_notes' => 'Buen progreso en la alineación. Paciente colaborador.',
                'progress_notes' => 'Tratamiento en curso. Buena respuesta del paciente.',
                'retention_plan' => 'Retenedores fijos y removibles al finalizar tratamiento activo.',
                'measurements' => [
                    'overjet_mm' => 3.5,
                    'overbite_mm' => 2.8,
                ],
            ]);
        }

        // -------------------------------------------------------------------
        // EndodonticsRecord — fillable keys + enum-safe treatment_status
        // -------------------------------------------------------------------
        $endodonticStatuses = ['in_progress', 'completed', 'retreatment'];
        $pulpDiagnoses = ['Pulpitis irreversible', 'Necrosis pulpar', 'Pulpitis reversible'];
        foreach ($patients->take(2)->values() as $index => $patient) {
            EndodonticsRecord::create([
                'patient_id' => $patient->id,
                'created_by' => $creatorId,
                'dental_piece_id' => $pieceIds[$index % count($pieceIds)],
                'tooth_number' => sprintf('%d', 11 + ($index * 5)),
                'canal_count' => 3 + ($index % 2),
                'canal_lengths' => [
                    'mesial_buccal' => 21.0,
                    'distal_buccal' => 20.5,
                    'palatal' => 22.0,
                ],
                'canal_diameters' => [
                    'mesial_buccal' => 0.30,
                    'distal_buccal' => 0.25,
                    'palatal' => 0.40,
                ],
                'working_length_method' => 'Localizador apical + radiografía',
                'pulp_diagnosis' => $pulpDiagnoses[$index % count($pulpDiagnoses)],
                'periapical_diagnosis' => 'Periodontitis apical crónica',
                'treatment_plan' => 'Conductometría, instrumentación rotatoria, obturación termoplástica',
                'anesthesia_used' => 'Lidocaína 2% con epinefrina 1:100000',
                'irrigation_protocol' => 'Hipoclorito de sodio 2.5% + EDTA 17%',
                'obturation_technique' => 'Condensación lateral con gutapercha',
                'obturation_materials' => 'Gutapercha + cemento sellador AH Plus',
                'treatment_status' => $endodonticStatuses[$index % count($endodonticStatuses)],
                'radiographic_measurements' => [
                    'working_length_mm' => 21.5,
                    'master_apex_size' => 35,
                ],
            ]);
        }

        // -------------------------------------------------------------------
        // RehabilitationRecord — prosthesis_type/delivery_date columns +
        // enum-safe status (impression/laboratory/try_in/delivered/cemented/failed)
        // -------------------------------------------------------------------
        $rehabStatuses = ['impression', 'laboratory', 'try_in', 'delivered', 'cemented'];
        $prosthesisTypes = ['crown', 'bridge', 'veneer', 'inlay', 'onlay'];
        $materialTypes = ['ceramic', 'zirconia', 'metal_ceramic', 'composite', 'pmma'];
        foreach ($patients->take(2)->values() as $index => $patient) {
            RehabilitationRecord::create([
                'patient_id' => $patient->id,
                'created_by' => $creatorId,
                'dental_piece_id' => $pieceIds[$index % count($pieceIds)],
                'prosthesis_type' => $prosthesisTypes[$index % count($prosthesisTypes)],
                'material_type' => $materialTypes[$index % count($materialTypes)],
                'laboratory_name' => 'Laboratorio Dental Pro',
                'laboratory_contact' => 'lab@dentalpro.test',
                'impression_date' => now()->subDays(rand(14, 21))->toDateString(),
                'delivery_date' => now()->subDays(rand(7, 13))->toDateString(),
                'cementation_date' => now()->subDays(rand(1, 6))->toDateString(),
                'shade_selection' => 'A2 - VITA classical',
                'try_in_notes' => 'Ajuste marginal correcto. Oclusión verificada.',
                'cementation_notes' => 'Cementado con ionómero de vidrio. Sin excedentes.',
                'status' => $rehabStatuses[$index % count($rehabStatuses)],
                'follow_up_notes' => 'Control programado en 1 semana.',
            ]);
        }

        // -------------------------------------------------------------------
        // OralSurgeryRecord — surgery_site + surgery_start_time/end_time +
        // surgery_duration_minutes + enum-safe status
        // -------------------------------------------------------------------
        $surgeryStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];
        $procedureTypes = ['extraction', 'implant_placement', 'biopsy', 'apicectomy'];
        $surgerySites = [
            'Tercer molar superior derecho',
            'Tercer molar inferior izquierdo',
            'Canino superior derecho retenido',
            'Lesión periapical en incisivo lateral superior izquierdo',
        ];
        foreach ($patients->take(2)->values() as $index => $patient) {
            $start = sprintf('%02d:%02d:00', 9 + $index, 30);
            $duration = 30 + ($index * 15);
            $endTime = (new \DateTimeImmutable($start))->modify("+{$duration} minutes")->format('H:i:s');

            OralSurgeryRecord::create([
                'patient_id' => $patient->id,
                'created_by' => $creatorId,
                'dental_piece_id' => $pieceIds[$index % count($pieceIds)],
                'procedure_type' => $procedureTypes[$index % count($procedureTypes)],
                'surgery_site' => $surgerySites[$index % count($surgerySites)],
                'surgical_technique' => 'Incisión sulcular + colgajo mucoperióstico',
                'anesthesia_type' => 'Lidocaína 2% con epinefrina 1:100000',
                'surgery_start_time' => $start,
                'surgery_end_time' => $endTime,
                'surgery_duration_minutes' => $duration,
                'sutures_used' => 'Seda 3-0',
                'suture_count' => 3 + $index,
                'post_surgical_instructions' => 'Aplicar hielo 15 min/h, dieta blanda 48h, AINES según indicación.',
                'medications_prescribed' => 'Ibuprofeno 400mg c/8h x 3 días, Paracetamol 500mg c/8h rescate',
                'status' => $surgeryStatuses[$index % count($surgeryStatuses)],
                'follow_up_notes' => 'Control en 7 días para retiro de suturas.',
            ]);
        }

        $this->command->info('Registros de especialidades creados exitosamente.');
    }
}