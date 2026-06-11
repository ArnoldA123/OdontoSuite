<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use App\Models\User;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\MedicalRecord;
use App\Models\ClinicalEvolution;
use App\Models\ImplantologyRecord;
use App\Models\OrthodonticsRecord;
use App\Models\EndodonticsRecord;
use App\Models\RehabilitationRecord;
use App\Models\OralSurgeryRecord;
use App\Models\Interconsultation;
use App\Models\DentalPiece;
use Carbon\Carbon;

class ClinicalDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener pacientes existentes
        $patients = Patient::take(5)->get();
        if ($patients->isEmpty()) {
            $this->command->info('No hay pacientes disponibles. Ejecuta primero PatientSeeder.');
            return;
        }

        // Obtener usuarios existentes
        $users = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental'])->take(3)->get();
        if ($users->isEmpty()) {
            $this->command->info('No hay usuarios clínicos disponibles. Ejecuta primero UserSeeder.');
            return;
        }

        // Obtener piezas dentales
        $dentalPieces = DentalPiece::take(10)->get();
        if ($dentalPieces->isEmpty()) {
            $this->command->info('No hay piezas dentales disponibles. Ejecuta primero DentalPieceSeeder.');
            return;
        }

        $this->command->info('Creando datos de prueba clínicos...');

        // Crear planes de tratamiento
        $this->createTreatmentPlans($patients, $users, $dentalPieces);

        // Crear presupuestos
        $this->createQuotations($patients, $users);

        // Crear historias clínicas
        $this->createMedicalRecords($patients, $users);

        // Crear registros de especialidades
        $this->createSpecialtyRecords($patients, $users, $dentalPieces);

        // Crear interconsultas
        $this->createInterconsultations($patients, $users);

        $this->command->info('Datos de prueba clínicos creados exitosamente.');
    }

    private function createTreatmentPlans($patients, $users, $dentalPieces)
    {
        $plans = [
            [
                'title' => 'Tratamiento de Caries Múltiples',
                'description' => 'Restauración de caries en piezas 16, 26, 36 y 46',
                'status' => 'approved',
                'total_cost' => 1200.00,
                'discount_amount' => 100.00,
                'final_cost' => 1100.00,
                'estimated_duration_weeks' => 4,
                'start_date' => Carbon::now()->addDays(7),
                'end_date' => Carbon::now()->addDays(35),
                'notes' => 'Paciente con múltiples caries que requieren atención inmediata',
                'patient_notes' => 'Paciente prefiere tratamiento en sesiones cortas',
                'requires_anesthesia' => true,
                'is_urgent' => false,
                'items' => [
                    [
                        'procedure_name' => 'Restauración con Resina Compuesta',
                        'description' => 'Restauración clase II en pieza 16',
                        'quantity' => 1,
                        'price' => 300.00,
                        'total_price' => 300.00,
                        'notes' => 'Material estético',
                        'dental_piece_id' => $dentalPieces->where('fdi_number', '16')->first()?->id,
                    ],
                    [
                        'procedure_name' => 'Restauración con Resina Compuesta',
                        'description' => 'Restauración clase II en pieza 26',
                        'quantity' => 1,
                        'price' => 300.00,
                        'total_price' => 300.00,
                        'notes' => 'Material estético',
                        'dental_piece_id' => $dentalPieces->where('fdi_number', '26')->first()?->id,
                    ],
                    [
                        'procedure_name' => 'Restauración con Resina Compuesta',
                        'description' => 'Restauración clase II en pieza 36',
                        'quantity' => 1,
                        'price' => 300.00,
                        'total_price' => 300.00,
                        'notes' => 'Material estético',
                        'dental_piece_id' => $dentalPieces->where('fdi_number', '36')->first()?->id,
                    ],
                    [
                        'procedure_name' => 'Restauración con Resina Compuesta',
                        'description' => 'Restauración clase II en pieza 46',
                        'quantity' => 1,
                        'price' => 300.00,
                        'total_price' => 300.00,
                        'notes' => 'Material estético',
                        'dental_piece_id' => $dentalPieces->where('fdi_number', '46')->first()?->id,
                    ],
                ]
            ],
            [
                'title' => 'Implante Dental Unitario',
                'description' => 'Colocación de implante en pieza 11',
                'status' => 'in_progress',
                'total_cost' => 2500.00,
                'discount_amount' => 0.00,
                'final_cost' => 2500.00,
                'estimated_duration_weeks' => 12,
                'start_date' => Carbon::now()->subDays(14),
                'end_date' => Carbon::now()->addDays(70),
                'notes' => 'Implante de titanio con corona de zirconio',
                'patient_notes' => 'Paciente con buena densidad ósea',
                'requires_anesthesia' => true,
                'is_urgent' => false,
                'items' => [
                    [
                        'procedure_name' => 'Implante de Titanio',
                        'description' => 'Implante de 4.0mm x 10mm',
                        'quantity' => 1,
                        'price' => 1500.00,
                        'total_price' => 1500.00,
                        'notes' => 'Marca Nobel Biocare',
                        'dental_piece_id' => $dentalPieces->where('fdi_number', '11')->first()?->id,
                    ],
                    [
                        'procedure_name' => 'Corona de Zirconio',
                        'description' => 'Corona individual de zirconio',
                        'quantity' => 1,
                        'price' => 1000.00,
                        'total_price' => 1000.00,
                        'notes' => 'Color A2',
                        'dental_piece_id' => $dentalPieces->where('fdi_number', '11')->first()?->id,
                    ],
                ]
            ],
            [
                'title' => 'Tratamiento de Endodoncia',
                'description' => 'Endodoncia en pieza 14',
                'status' => 'completed',
                'total_cost' => 800.00,
                'discount_amount' => 50.00,
                'final_cost' => 750.00,
                'estimated_duration_weeks' => 2,
                'start_date' => Carbon::now()->subDays(30),
                'end_date' => Carbon::now()->subDays(16),
                'notes' => 'Endodoncia unirradicular',
                'patient_notes' => 'Paciente sin complicaciones',
                'requires_anesthesia' => true,
                'is_urgent' => false,
                'items' => [
                    [
                        'procedure_name' => 'Endodoncia Unirradicular',
                        'description' => 'Tratamiento de conducto en pieza 14',
                        'quantity' => 1,
                        'price' => 800.00,
                        'total_price' => 800.00,
                        'notes' => 'Técnica rotatoria',
                        'dental_piece_id' => $dentalPieces->where('fdi_number', '14')->first()?->id,
                    ],
                ]
            ]
        ];

        foreach ($plans as $planData) {
            $plan = TreatmentPlan::create([
                'patient_id' => $patients->random()->id,
                'created_by' => $users->random()->id,
                'plan_number' => 'TP-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'title' => $planData['title'],
                'description' => $planData['description'],
                'status' => $planData['status'],
                'total_cost' => $planData['total_cost'],
                'discount_amount' => $planData['discount_amount'],
                'final_cost' => $planData['final_cost'],
                'estimated_duration_weeks' => $planData['estimated_duration_weeks'],
                'start_date' => $planData['start_date'],
                'end_date' => $planData['end_date'],
                'notes' => $planData['notes'],
                'patient_notes' => $planData['patient_notes'],
                'requires_anesthesia' => $planData['requires_anesthesia'],
                'is_urgent' => $planData['is_urgent'],
            ]);

            // Crear items del plan
            foreach ($planData['items'] as $itemData) {
                TreatmentPlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'procedure_name' => $itemData['procedure_name'],
                    'procedure_description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_cost' => $itemData['price'],
                    'total_cost' => $itemData['total_price'],
                    'notes' => $itemData['notes'],
                    'dental_piece_id' => $itemData['dental_piece_id'],
                ]);
            }
        }
    }

    private function createQuotations($patients, $users)
    {
        $treatmentPlans = TreatmentPlan::take(3)->get();

        foreach ($treatmentPlans as $plan) {
            $quotation = Quotation::create([
                'patient_id' => $plan->patient_id,
                'treatment_plan_id' => $plan->id,
                'created_by' => $users->random()->id,
                'quotation_number' => 'Q-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'quotation_date' => Carbon::now()->subDays(rand(1, 30)),
                'valid_until' => Carbon::now()->addDays(30),
                'subtotal' => $plan->total_cost,
                'discount_percentage' => $plan->discount_amount > 0 ? ($plan->discount_amount / $plan->total_cost) * 100 : 0,
                'discount_amount' => $plan->discount_amount,
                'tax_percentage' => 18.0,
                'tax_amount' => ($plan->final_cost * 18) / 100,
                'total_amount' => $plan->final_cost + (($plan->final_cost * 18) / 100),
                'status' => ['draft', 'sent', 'approved', 'rejected'][rand(0, 3)],
                'terms_conditions' => 'Válido por 30 días. Pago al contado tiene 5% de descuento.',
                'notes' => 'Presupuesto generado desde plan de tratamiento',
                'payment_terms' => ['50% al inicio', '50% al finalizar'],
            ]);

            // Crear items del presupuesto
            foreach ($plan->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_name' => $item->procedure_name,
                    'item_description' => $item->procedure_description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_cost,
                    'total_price' => $item->total_cost,
                    'notes' => $item->notes,
                ]);
            }
        }
    }

    private function createMedicalRecords($patients, $users)
    {
        $medicalRecords = [
            [
                'record_number' => 'HC-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'first_visit_date' => Carbon::now()->subDays(60),
                'chief_complaint' => 'Dolor en pieza 16',
                'medical_history' => 'Hipertensión controlada con medicamentos',
                'dental_history' => 'Múltiples restauraciones previas',
                'allergies' => 'Penicilina',
                'medications' => 'Losartán 50mg diario',
                'systemic_conditions' => 'Hipertensión arterial',
                'family_history' => 'Padre con diabetes',
                'social_history' => 'No fumador, consumo ocasional de alcohol',
                'vital_signs' => [
                    'blood_pressure' => '120/80',
                    'heart_rate' => 72,
                    'temperature' => 36.5
                ],
                'clinical_examination' => 'Caries profunda en pieza 16 con exposición pulpar',
                'diagnosis' => 'Pulpitis irreversible en pieza 16',
                'treatment_plan_summary' => 'Endodoncia y restauración con corona',
                'notes' => 'Paciente colaborador, sin complicaciones',
                'is_active' => true,
            ],
            [
                'record_number' => 'HC-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'first_visit_date' => Carbon::now()->subDays(45),
                'chief_complaint' => 'Falta de pieza 11',
                'medical_history' => 'Sin antecedentes médicos relevantes',
                'dental_history' => 'Extracción traumática hace 2 años',
                'allergies' => 'Ninguna conocida',
                'medications' => 'Ninguna',
                'systemic_conditions' => 'Ninguna',
                'family_history' => 'Madre con pérdida dental precoz',
                'social_history' => 'No fumador, deportista',
                'vital_signs' => [
                    'blood_pressure' => '110/70',
                    'heart_rate' => 65,
                    'temperature' => 36.2
                ],
                'clinical_examination' => 'Edentulismo en zona anterior superior',
                'diagnosis' => 'Edentulismo unitario en pieza 11',
                'treatment_plan_summary' => 'Implante dental con corona',
                'notes' => 'Paciente joven, buena calidad ósea',
                'is_active' => true,
            ]
        ];

        foreach ($medicalRecords as $recordData) {
            $record = MedicalRecord::create([
                'patient_id' => $patients->random()->id,
                'created_by' => $users->random()->id,
                'record_number' => $recordData['record_number'],
                'first_visit_date' => $recordData['first_visit_date'],
                'chief_complaint' => $recordData['chief_complaint'],
                'medical_history' => $recordData['medical_history'],
                'dental_history' => $recordData['dental_history'],
                'allergies' => $recordData['allergies'],
                'medications' => $recordData['medications'],
                'systemic_conditions' => $recordData['systemic_conditions'],
                'family_history' => $recordData['family_history'],
                'social_history' => $recordData['social_history'],
                'vital_signs' => $recordData['vital_signs'],
                'clinical_examination' => $recordData['clinical_examination'],
                'diagnosis' => $recordData['diagnosis'],
                'treatment_plan' => $recordData['treatment_plan_summary'],
                'notes' => $recordData['notes'],
                'is_active' => $recordData['is_active'],
            ]);

            // Crear evoluciones clínicas
            $this->createClinicalEvolutions($record, $users);
        }
    }

    private function createClinicalEvolutions($medicalRecord, $users)
    {
        $evolutions = [
            [
                'evolution_date' => Carbon::now()->subDays(30),
                'specialty' => 'Odontología General',
                'subjective' => 'Paciente refiere dolor moderado',
                'objective' => 'Caries profunda visible, prueba de vitalidad negativa',
                'assessment' => 'Pulpitis irreversible',
                'plan' => 'Endodoncia en próxima sesión',
                'procedures_performed' => [
                    [
                        'procedure_name' => 'Examen clínico',
                        'dental_pieces' => ['16'],
                        'notes' => 'Prueba de vitalidad negativa'
                    ]
                ],
                'materials_used' => [
                    [
                        'name' => 'Anestesia local',
                        'quantity' => 1,
                        'unit' => 'carpule'
                    ]
                ],
                'prescriptions' => [
                    [
                        'medication' => 'Ibuprofeno 600mg',
                        'dosage' => 'Cada 8 horas',
                        'instructions' => 'Por 3 días'
                    ]
                ],
                'recommendations' => 'Evitar alimentos duros',
                'next_appointment_notes' => 'Cita para endodoncia',
                'requires_follow_up' => true,
                'follow_up_date' => Carbon::now()->addDays(7),
                'notes' => 'Paciente colaborador',
            ],
            [
                'evolution_date' => Carbon::now()->subDays(15),
                'specialty' => 'Endodoncia',
                'subjective' => 'Paciente asintomático',
                'objective' => 'Endodoncia completada, restauración temporal colocada',
                'assessment' => 'Tratamiento exitoso',
                'plan' => 'Restauración definitiva en próxima sesión',
                'procedures_performed' => [
                    [
                        'procedure_name' => 'Endodoncia unirradicular',
                        'dental_pieces' => ['16'],
                        'notes' => 'Técnica rotatoria'
                    ]
                ],
                'materials_used' => [
                    [
                        'name' => 'Gutapercha',
                        'quantity' => 1,
                        'unit' => 'cono'
                    ],
                    [
                        'name' => 'Cemento endodóntico',
                        'quantity' => 1,
                        'unit' => 'ml'
                    ]
                ],
                'prescriptions' => [],
                'recommendations' => 'Mantener higiene oral adecuada',
                'next_appointment_notes' => 'Cita para restauración definitiva',
                'requires_follow_up' => true,
                'follow_up_date' => Carbon::now()->addDays(14),
                'notes' => 'Procedimiento sin complicaciones',
            ]
        ];

        foreach ($evolutions as $evolutionData) {
            ClinicalEvolution::create([
                'patient_id' => $medicalRecord->patient_id,
                'medical_record_id' => $medicalRecord->id,
                'created_by' => $users->random()->id,
                'evolution_date' => $evolutionData['evolution_date'],
                'specialty' => $evolutionData['specialty'],
                'subjective' => $evolutionData['subjective'],
                'objective' => $evolutionData['objective'],
                'assessment' => $evolutionData['assessment'],
                'plan' => $evolutionData['plan'],
                'procedures_performed' => json_encode($evolutionData['procedures_performed']),
                'materials_used' => json_encode($evolutionData['materials_used']),
                'prescriptions' => json_encode($evolutionData['prescriptions']),
                'recommendations' => $evolutionData['recommendations'],
                'next_appointment_notes' => $evolutionData['next_appointment_notes'],
                'requires_follow_up' => $evolutionData['requires_follow_up'],
                'follow_up_date' => $evolutionData['follow_up_date'],
            ]);
        }
    }

    private function createSpecialtyRecords($patients, $users, $dentalPieces)
    {
        // Registro de Implantología
        ImplantologyRecord::create([
            'patient_id' => $patients->random()->id,
            'created_by' => $users->where('role', 'implantologo')->first()?->id ?? $users->random()->id,
            'dental_piece_id' => $dentalPieces->where('fdi_number', '11')->first()?->id,
            'implant_brand' => 'Nobel Biocare',
            'implant_model' => 'NobelActive',
            'implant_diameter' => 4.0,
            'implant_length' => 10.0,
            'batch_number' => 'NB2024001',
            'serial_number' => 'SN123456',
            'placement_date' => Carbon::now()->subDays(14),
            'healing_date' => Carbon::now()->addDays(90),
            'loading_date' => null,
            'status' => 'healing',
            'surgical_notes' => 'Colocación sin complicaciones, buena densidad ósea',
            'post_surgical_notes' => 'Paciente asintomático, cicatrización normal',
            'complications' => json_encode([]),
            'radiographic_data' => json_encode([
                'pre_op' => 'Buena altura y ancho óseo',
                'post_op' => 'Implante bien posicionado'
            ]),
            'measurements' => json_encode([
                'bone_height' => 12.0,
                'bone_width' => 8.0
            ]),
            'torque_value' => 35,
            'follow_up_notes' => 'Control en 3 meses',
        ]);

        // Registro de Ortodoncia
        OrthodonticsRecord::create([
            'patient_id' => $patients->random()->id,
            'created_by' => $users->where('role', 'odontologo')->first()?->id ?? $users->random()->id,
            'treatment_type' => 'brackets',
            'appliance_type' => 'Brackets metálicos',
            'treatment_start_date' => Carbon::now()->subDays(180),
            'estimated_completion_date' => Carbon::now()->addDays(365),
            'treatment_phase' => 'active',
            'treatment_objectives' => 'Corrección de maloclusión clase II',
            'current_notes' => 'Buen progreso, alineación mejorando',
            'progress_notes' => 'Paciente colaborador, buen progreso',
        ]);

        // Registro de Endodoncia
        EndodonticsRecord::create([
            'patient_id' => $patients->random()->id,
            'created_by' => $users->where('role', 'odontologo')->first()?->id ?? $users->random()->id,
            'dental_piece_id' => $dentalPieces->where('fdi_number', '14')->first()?->id,
            'tooth_number' => '14',
            'canal_count' => 1,
            'pulp_diagnosis' => 'Necrosis pulpar',
            'periapical_diagnosis' => 'Lesión periapical',
            'treatment_plan' => 'Endodoncia unirradicular',
            'anesthesia_used' => 'Lidocaína 2%',
            'working_length_method' => 'Localizador electrónico',
            'canal_lengths' => json_encode(['21mm']),
            'canal_diameters' => json_encode(['0.25mm']),
            'irrigation_protocol' => 'Hipoclorito de sodio 2.5%',
            'obturation_technique' => 'Técnica de condensación lateral',
            'obturation_materials' => 'Gutapercha + cemento endodóntico',
            'complications' => json_encode([]),
            'treatment_status' => 'completed',
            'treatment_completion_date' => Carbon::now()->subDays(30),
            'follow_up_notes' => 'Control en 6 meses',
        ]);

        // Registro de Rehabilitación
        RehabilitationRecord::create([
            'patient_id' => $patients->random()->id,
            'created_by' => $users->where('role', 'odontologo')->first()?->id ?? $users->random()->id,
            'dental_piece_id' => $dentalPieces->where('fdi_number', '11')->first()?->id,
            'prosthesis_type' => 'Corona individual',
            'material_type' => 'Zirconio',
            'laboratory_name' => 'Laboratorio Dental Pro',
            'laboratory_contact' => 'Juan Pérez',
            'impression_date' => Carbon::now()->subDays(7),
            'delivery_date' => Carbon::now()->addDays(14),
            'cementation_date' => null,
            'shade_selection' => 'A2',
            'impression_notes' => 'Impresión digital',
            'status' => 'laboratory',
            'follow_up_notes' => 'Control en 1 mes',
        ]);

        // Registro de Cirugía Oral
        OralSurgeryRecord::create([
            'patient_id' => $patients->random()->id,
            'created_by' => $users->where('role', 'odontologo')->first()?->id ?? $users->random()->id,
            'dental_piece_id' => $dentalPieces->where('fdi_number', '18')->first()?->id,
            'procedure_type' => 'Extracción de tercer molar',
            'surgery_site' => 'Cuadrante inferior izquierdo',
            'surgical_technique' => 'Extracción simple',
            'anesthesia_type' => 'Anestesia local',
            'anesthesia_amount' => '2 carpules',
            'surgery_start_time' => '09:00',
            'surgery_end_time' => '09:30',
            'surgery_duration_minutes' => 30,
            'surgical_notes' => 'Extracción simple, sin complicaciones',
            'complications' => json_encode([]),
            'sutures_used' => 'Seda 3-0',
            'suture_count' => 2,
            'hemostasis_method' => 'Compresión',
            'post_surgical_instructions' => 'Aplicar hielo, dieta blanda por 3 días',
            'status' => 'completed',
            'follow_up_date' => Carbon::now()->addDays(7),
            'follow_up_notes' => 'Control de cicatrización',
        ]);
    }

    private function createInterconsultations($patients, $users)
    {
        $interconsultations = [
            [
                'subject' => 'Evaluación para implante',
                'reason' => 'Paciente requiere evaluación de densidad ósea',
                'question' => '¿Es viable la colocación de implante en pieza 11?',
                'status' => 'completed',
                'requested_at' => Carbon::now()->subDays(20),
                'responded_at' => Carbon::now()->subDays(18),
                'completed_at' => Carbon::now()->subDays(15),
                'response_notes' => 'Paciente con buena densidad ósea, viable para implante',
                'recommendations' => 'Proceder con implante de 4.0mm x 10mm',
                'attachments' => [],
            ],
            [
                'subject' => 'Evaluación de ortodoncia',
                'reason' => 'Paciente con maloclusión severa',
                'question' => '¿Es candidato para tratamiento ortodóntico?',
                'status' => 'in_progress',
                'requested_at' => Carbon::now()->subDays(10),
                'responded_at' => Carbon::now()->subDays(8),
                'completed_at' => null,
                'response_notes' => 'Paciente candidato, requiere tratamiento de 18 meses',
                'recommendations' => 'Iniciar con brackets metálicos',
                'attachments' => [],
            ]
        ];

        foreach ($interconsultations as $interconsultationData) {
            Interconsultation::create([
                'patient_id' => $patients->random()->id,
                'from_specialist_id' => $users->random()->id,
                'to_specialist_id' => $users->where('id', '!=', $users->random()->id)->first()?->id ?? $users->random()->id,
                'specialty_from' => 'Odontología General',
                'specialty_to' => 'Implantología',
                'reason' => $interconsultationData['reason'],
                'clinical_question' => $interconsultationData['question'],
                'status' => $interconsultationData['status'],
                'response' => $interconsultationData['response_notes'],
                'recommendations' => $interconsultationData['recommendations'],
                'requested_date' => $interconsultationData['requested_at'],
                'response_date' => $interconsultationData['responded_at'],
                'follow_up_date' => $interconsultationData['completed_at'],
            ]);
        }
    }
}
