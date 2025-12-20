<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\User;
use App\Models\Branch;
use App\Models\TreatmentPlan;
use Carbon\Carbon;

class CompletedAppointmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener datos existentes
        $branch = Branch::first();
        $appointmentTypes = AppointmentType::all();
        $professionals = User::whereIn('role', ['dentista', 'administrador', 'recepcionista'])->get();

        if (!$branch || $appointmentTypes->isEmpty() || $professionals->isEmpty()) {
            $this->command->warn('No hay sucursales, tipos de cita o profesionales. Ejecuta primero los seeders básicos.');
            return;
        }

        // Crear pacientes adicionales si no existen suficientes
        $patients = Patient::all();
        if ($patients->count() < 10) {
            $this->createAdditionalPatients();
            $patients = Patient::all();
        }

        // Crear planes de tratamiento si no existen
        // $this->createTreatmentPlans(); // Comentado temporalmente

        // Crear citas completadas de los últimos 30 días
        $this->createCompletedAppointments($patients, $appointmentTypes, $professionals, $branch);
    }

    private function createAdditionalPatients()
    {
        $patients = [
            [
                'first_name' => 'María Elena',
                'last_name' => 'Rodríguez García',
                'document_number' => '12345678',
                'email' => 'maria.rodriguez@email.com',
                'phone' => '+51 987 654 321',
                'birth_date' => '1985-03-15',
                'gender' => 'female',
                'address' => 'Av. Principal 123, San Isidro, Lima',
                'emergency_contact_name' => 'Carlos Rodríguez',
                'emergency_contact_phone' => '+51 987 654 322',
                'medical_history' => 'Hipertensión controlada con medicación',
                'allergies' => 'Penicilina',
                'notes' => 'Paciente regular, buena higiene bucal'
            ],
            [
                'first_name' => 'Carlos Alberto',
                'last_name' => 'Mendoza Torres',
                'document_number' => '23456789',
                'email' => 'carlos.mendoza@email.com',
                'phone' => '+51 987 654 323',
                'birth_date' => '1978-07-22',
                'gender' => 'male',
                'address' => 'Jr. Los Olivos 456, Miraflores, Lima',
                'emergency_contact_name' => 'Ana Mendoza',
                'emergency_contact_phone' => '+51 987 654 324',
                'medical_history' => 'Diabetes tipo 2, controlada con dieta',
                'allergies' => 'Ninguna conocida',
                'notes' => 'Paciente colaborador, asiste puntualmente'
            ],
            [
                'first_name' => 'Ana Patricia',
                'last_name' => 'Silva Vega',
                'document_number' => '34567890',
                'email' => 'ana.silva@email.com',
                'phone' => '+51 987 654 325',
                'birth_date' => '1992-11-08',
                'gender' => 'female',
                'address' => 'Av. Universitaria 789, La Molina, Lima',
                'emergency_contact_name' => 'Roberto Silva',
                'emergency_contact_phone' => '+51 987 654 326',
                'medical_history' => 'Sin antecedentes patológicos',
                'allergies' => 'Látex',
                'notes' => 'Paciente joven, excelente higiene bucal'
            ],
            [
                'first_name' => 'Roberto Carlos',
                'last_name' => 'Torres Herrera',
                'document_number' => '45678901',
                'email' => 'roberto.torres@email.com',
                'phone' => '+51 987 654 327',
                'birth_date' => '1980-01-30',
                'gender' => 'male',
                'address' => 'Jr. San Martín 321, Surco, Lima',
                'emergency_contact_name' => 'Lucía Torres',
                'emergency_contact_phone' => '+51 987 654 328',
                'medical_history' => 'Asma leve, controlado',
                'allergies' => 'Polen, ácaros',
                'notes' => 'Paciente deportista, requiere atención especial'
            ],
            [
                'first_name' => 'Lucía Esperanza',
                'last_name' => 'Vega Flores',
                'document_number' => '56789012',
                'email' => 'lucia.vega@email.com',
                'phone' => '+51 987 654 329',
                'birth_date' => '1987-09-14',
                'gender' => 'female',
                'address' => 'Av. Brasil 654, Jesús María, Lima',
                'emergency_contact_name' => 'Miguel Vega',
                'emergency_contact_phone' => '+51 987 654 330',
                'medical_history' => 'Embarazo de 6 meses',
                'allergies' => 'Ninguna conocida',
                'notes' => 'Paciente embarazada, requiere cuidados especiales'
            ],
            [
                'first_name' => 'Miguel Ángel',
                'last_name' => 'Herrera Morales',
                'document_number' => '67890123',
                'email' => 'miguel.herrera@email.com',
                'phone' => '+51 987 654 331',
                'birth_date' => '1975-12-03',
                'gender' => 'male',
                'address' => 'Jr. Libertad 987, Pueblo Libre, Lima',
                'emergency_contact_name' => 'Carmen Herrera',
                'emergency_contact_phone' => '+51 987 654 332',
                'medical_history' => 'Hipertensión arterial',
                'allergies' => 'Sulfamidas',
                'notes' => 'Paciente con ansiedad dental, requiere sedación'
            ],
            [
                'first_name' => 'Carmen Rosa',
                'last_name' => 'Flores Rojas',
                'document_number' => '78901234',
                'email' => 'carmen.flores@email.com',
                'phone' => '+51 987 654 333',
                'birth_date' => '1990-05-18',
                'gender' => 'female',
                'address' => 'Av. Arequipa 147, Lince, Lima',
                'emergency_contact_name' => 'Fernando Flores',
                'emergency_contact_phone' => '+51 987 654 334',
                'medical_history' => 'Sin antecedentes patológicos',
                'allergies' => 'Ninguna conocida',
                'notes' => 'Paciente modelo, excelente colaboración'
            ],
            [
                'first_name' => 'Fernando José',
                'last_name' => 'Morales Castro',
                'document_number' => '89012345',
                'email' => 'fernando.morales@email.com',
                'phone' => '+51 987 654 335',
                'birth_date' => '1983-08-25',
                'gender' => 'male',
                'address' => 'Jr. Tacna 258, Cercado de Lima',
                'emergency_contact_name' => 'Sofía Morales',
                'emergency_contact_phone' => '+51 987 654 336',
                'medical_history' => 'Gastritis crónica',
                'allergies' => 'Ibuprofeno',
                'notes' => 'Paciente con reflujo gastroesofágico'
            ],
            [
                'first_name' => 'Sofía Alejandra',
                'last_name' => 'Rojas Paredes',
                'document_number' => '90123456',
                'email' => 'sofia.rojas@email.com',
                'phone' => '+51 987 654 337',
                'birth_date' => '1995-04-12',
                'gender' => 'female',
                'address' => 'Av. Javier Prado 369, San Borja, Lima',
                'emergency_contact_name' => 'Diego Rojas',
                'emergency_contact_phone' => '+51 987 654 338',
                'medical_history' => 'Sin antecedentes patológicos',
                'allergies' => 'Ninguna conocida',
                'notes' => 'Paciente universitaria, horarios flexibles'
            ],
            [
                'first_name' => 'Diego Armando',
                'last_name' => 'Castro Mendoza',
                'document_number' => '01234567',
                'email' => 'diego.castro@email.com',
                'phone' => '+51 987 654 339',
                'birth_date' => '1988-10-07',
                'gender' => 'male',
                'address' => 'Jr. Ayacucho 741, Breña, Lima',
                'emergency_contact_name' => 'Patricia Castro',
                'emergency_contact_phone' => '+51 987 654 340',
                'medical_history' => 'Artritis reumatoide leve',
                'allergies' => 'Metotrexato',
                'notes' => 'Paciente con movilidad reducida'
            ]
        ];

        foreach ($patients as $patientData) {
            Patient::create($patientData);
        }
    }

    private function createTreatmentPlans()
    {
        // Crear planes de tratamiento para algunos pacientes
        $patients = Patient::take(5)->get();
        $users = User::take(3)->get();

        foreach ($patients as $index => $patient) {
            $treatmentPlans = [
                [
                    'patient_id' => $patient->id,
                    'created_by' => $users->random()->id,
                    'plan_number' => 'TP-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'title' => 'Limpieza Dental Completa',
                    'description' => 'Limpieza profunda, eliminación de sarro y placa bacteriana',
                    'status' => 'approved',
                    'total_cost' => 150.00,
                    'final_cost' => 150.00
                ],
                [
                    'patient_id' => $patient->id,
                    'created_by' => $users->random()->id,
                    'plan_number' => 'TP-' . str_pad($index + 6, 4, '0', STR_PAD_LEFT),
                    'title' => 'Tratamiento de Caries',
                    'description' => 'Obturación con resina compuesta',
                    'status' => 'approved',
                    'total_cost' => 300.00,
                    'final_cost' => 300.00
                ],
                [
                    'patient_id' => $patient->id,
                    'created_by' => $users->random()->id,
                    'plan_number' => 'TP-' . str_pad($index + 11, 4, '0', STR_PAD_LEFT),
                    'title' => 'Ortodoncia Inicial',
                    'description' => 'Evaluación y plan de tratamiento ortodóntico',
                    'status' => 'approved',
                    'total_cost' => 500.00,
                    'final_cost' => 500.00
                ]
            ];

            foreach ($treatmentPlans as $planData) {
                TreatmentPlan::create($planData);
            }
        }
    }

    private function createCompletedAppointments($patients, $appointmentTypes, $professionals, $branch)
    {
        $today = Carbon::today();
        $dentalChair = \App\Models\DentalChair::first();

        if (!$dentalChair) {
            $this->command->warn('No hay sillas dentales disponibles. Creando una...');
            $dentalChair = \App\Models\DentalChair::create([
                'name' => 'Silla 1',
                'location' => 'Consultorio 1',
                'is_active' => true
            ]);
        }

        // Crear citas del DÍA ACTUAL que ya terminaron
        $appointments = [
            [
                'patient' => $patients[0],
                'appointment_type' => $appointmentTypes->where('name', 'Consulta General')->first() ?? $appointmentTypes->first(),
                'professional' => $professionals->where('role', 'dentista')->first() ?? $professionals->first(),
                'scheduled_at' => $today->copy()->setTime(8, 0),
                'duration_minutes' => 60,
                'notes' => 'Consulta de rutina, paciente asintomático',
                'treatment_notes' => 'Limpieza dental realizada, sin hallazgos patológicos'
            ],
            [
                'patient' => $patients[1],
                'appointment_type' => $appointmentTypes->where('name', 'Ortodoncia')->first() ?? $appointmentTypes->first(),
                'professional' => $professionals->where('role', 'dentista')->first() ?? $professionals->first(),
                'scheduled_at' => $today->copy()->setTime(9, 30),
                'duration_minutes' => 90,
                'notes' => 'Ajuste de brackets, progreso satisfactorio',
                'treatment_notes' => 'Ajuste realizado en arco superior, próxima cita en 4 semanas'
            ],
            [
                'patient' => $patients[2],
                'appointment_type' => $appointmentTypes->where('name', 'Caries')->first() ?? $appointmentTypes->first(),
                'professional' => $professionals->where('role', 'dentista')->first() ?? $professionals->first(),
                'scheduled_at' => $today->copy()->setTime(11, 0),
                'duration_minutes' => 45,
                'notes' => 'Caries en molar superior, requiere obturación',
                'treatment_notes' => 'Obturación con resina compuesta en molar 16, sin complicaciones'
            ],
            [
                'patient' => $patients[3],
                'appointment_type' => $appointmentTypes->where('name', 'Endodoncia')->first() ?? $appointmentTypes->first(),
                'professional' => $professionals->where('role', 'dentista')->first() ?? $professionals->first(),
                'scheduled_at' => $today->copy()->setTime(14, 0),
                'duration_minutes' => 120,
                'notes' => 'Dolor intenso, requiere tratamiento de conducto',
                'treatment_notes' => 'Endodoncia en molar 26, primera sesión completada, próxima cita en 1 semana'
            ],
            [
                'patient' => $patients[4],
                'appointment_type' => $appointmentTypes->where('name', 'Prótesis')->first() ?? $appointmentTypes->first(),
                'professional' => $professionals->where('role', 'dentista')->first() ?? $professionals->first(),
                'scheduled_at' => $today->copy()->setTime(16, 0),
                'duration_minutes' => 75,
                'notes' => 'Pérdida de dientes posteriores, requiere prótesis',
                'treatment_notes' => 'Toma de impresiones para prótesis parcial removible'
            ],
            [
                'patient' => $patients[5],
                'appointment_type' => $appointmentTypes->where('name', 'Consulta General')->first() ?? $appointmentTypes->first(),
                'professional' => $professionals->where('role', 'dentista')->first() ?? $professionals->first(),
                'scheduled_at' => $today->copy()->setTime(17, 30),
                'duration_minutes' => 30,
                'notes' => 'Control post-tratamiento, evolución favorable',
                'treatment_notes' => 'Control de limpieza, evolución excelente, próxima cita en 6 meses'
            ]
        ];

        foreach ($appointments as $data) {
            $scheduledAt = $data['scheduled_at'];
            $endsAt = $scheduledAt->copy()->addMinutes($data['duration_minutes']);

            // Solo marcar como completed si ya pasó la hora de finalización
            $status = Carbon::now()->gte($endsAt) ? 'completed' : 'in_progress';

            Appointment::create([
                'patient_id' => $data['patient']->id,
                'appointment_type_id' => $data['appointment_type']->id,
                'user_id' => $data['professional']->id,
                'dental_chair_id' => $dentalChair->id,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $data['duration_minutes'],
                'status' => $status,
                'notes' => $data['notes'],
                'treatment_notes' => $data['treatment_notes'],
                'created_by' => $data['professional']->id,
                'updated_by' => $data['professional']->id,
                'created_at' => $scheduledAt->copy()->subHours(24), // Creada ayer
                'updated_at' => $endsAt, // Actualizada al terminar
            ]);
        }

        $this->command->info('Creadas ' . count($appointments) . ' citas del día actual con pagos pendientes.');
    }
}
