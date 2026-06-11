<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        DB::table('appointments')->delete();
        DB::table('patients')->delete();
        DB::table('dental_chairs')->delete();
        DB::table('appointment_types')->delete();
        DB::table('users')->where('id', '!=', 1)->delete();

        // Create appointment types
        $appointmentTypes = [
            ['name' => 'Consulta General', 'description' => 'Consulta odontológica general', 'default_duration_minutes' => 30, 'price' => 50.00, 'color' => '#3B82F6'],
            ['name' => 'Limpieza Dental', 'description' => 'Limpieza y profilaxis dental', 'default_duration_minutes' => 45, 'price' => 80.00, 'color' => '#10B981'],
            ['name' => 'Extracción', 'description' => 'Extracción de pieza dental', 'default_duration_minutes' => 60, 'price' => 120.00, 'color' => '#EF4444'],
            ['name' => 'Ortodoncia', 'description' => 'Consulta de ortodoncia', 'default_duration_minutes' => 90, 'price' => 150.00, 'color' => '#8B5CF6'],
            ['name' => 'Endodoncia', 'description' => 'Tratamiento de conducto', 'default_duration_minutes' => 120, 'price' => 200.00, 'color' => '#F59E0B'],
            ['name' => 'Implante', 'description' => 'Colocación de implante dental', 'default_duration_minutes' => 180, 'price' => 500.00, 'color' => '#6B7280']
        ];

        foreach ($appointmentTypes as $type) {
            DB::table('appointment_types')->insert([
                'name' => $type['name'],
                'description' => $type['description'],
                'default_duration_minutes' => $type['default_duration_minutes'],
                'price' => $type['price'],
                'color' => $type['color'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create dental chairs
        $dentalChairs = [
            ['name' => 'Consultorio 1', 'code' => 'C001', 'description' => 'Consultorio principal', 'is_active' => true],
            ['name' => 'Consultorio 2', 'code' => 'C002', 'description' => 'Consultorio secundario', 'is_active' => true],
            ['name' => 'Consultorio 3', 'code' => 'C003', 'description' => 'Consultorio de ortodoncia', 'is_active' => true],
            ['name' => 'Consultorio 4', 'code' => 'C004', 'description' => 'Consultorio de cirugía', 'is_active' => true],
            ['name' => 'Consultorio 5', 'code' => 'C005', 'description' => 'Consultorio de emergencias', 'is_active' => true]
        ];

        foreach ($dentalChairs as $chair) {
            DB::table('dental_chairs')->insert([
                'name' => $chair['name'],
                'code' => $chair['code'],
                'description' => $chair['description'],
                'is_active' => $chair['is_active'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create professionals
        $professionals = [
            ['name' => 'Dr. Carlos Mendoza', 'email' => 'carlos.mendoza@odontosuite.com', 'username' => 'carlos.mendoza', 'phone' => '+51 987 654 321', 'role' => 'odontologo', 'specialty' => 'Odontología General'],
            ['name' => 'Dra. Ana García', 'email' => 'ana.garcia@odontosuite.com', 'username' => 'ana.garcia', 'phone' => '+51 987 654 322', 'role' => 'odontologo', 'specialty' => 'Ortodoncia'],
            ['name' => 'Dr. Luis Rodríguez', 'email' => 'luis.rodriguez@odontosuite.com', 'username' => 'luis.rodriguez', 'phone' => '+51 987 654 323', 'role' => 'odontologo', 'specialty' => 'Endodoncia'],
            ['name' => 'Dra. María López', 'email' => 'maria.lopez@odontosuite.com', 'username' => 'maria.lopez', 'phone' => '+51 987 654 324', 'role' => 'odontologo', 'specialty' => 'Cirugía Oral'],
            ['name' => 'Dr. Pedro Silva', 'email' => 'pedro.silva@odontosuite.com', 'username' => 'pedro.silva', 'phone' => '+51 987 654 325', 'role' => 'odontologo', 'specialty' => 'Periodoncia']
        ];

        foreach ($professionals as $professional) {
            DB::table('users')->insert([
                'name' => $professional['name'],
                'email' => $professional['email'],
                'username' => $professional['username'],
                'phone' => $professional['phone'],
                'password' => Hash::make('password123'),
                'role' => $professional['role'],
                'specialty' => $professional['specialty'],
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create patients
        $patients = [
            ['first_name' => 'Juan', 'last_name' => 'Pérez', 'email' => 'juan.perez@email.com', 'phone' => '+51 987 123 456', 'birth_date' => '1985-03-15', 'gender' => 'male', 'address' => 'Av. Principal 123, Lima', 'emergency_contact_name' => 'María Pérez', 'emergency_contact_phone' => '+51 987 123 457', 'medical_history' => 'Sin alergias conocidas', 'notes' => 'Primera consulta'],
            ['first_name' => 'María', 'last_name' => 'González', 'email' => 'maria.gonzalez@email.com', 'phone' => '+51 987 123 458', 'birth_date' => '1990-07-22', 'gender' => 'female', 'address' => 'Jr. Los Olivos 456, Lima', 'emergency_contact_name' => 'Carlos González', 'emergency_contact_phone' => '+51 987 123 459', 'medical_history' => 'Hipertensión controlada', 'notes' => 'Tratamiento de ortodoncia previo'],
            ['first_name' => 'Carlos', 'last_name' => 'Martínez', 'email' => 'carlos.martinez@email.com', 'phone' => '+51 987 123 460', 'birth_date' => '1978-11-08', 'gender' => 'male', 'address' => 'Av. Brasil 789, Lima', 'emergency_contact_name' => 'Ana Martínez', 'emergency_contact_phone' => '+51 987 123 461', 'medical_history' => 'Diabetes tipo 2', 'notes' => 'Múltiples extracciones'],
            ['first_name' => 'Ana', 'last_name' => 'Rodríguez', 'email' => 'ana.rodriguez@email.com', 'phone' => '+51 987 123 462', 'birth_date' => '1995-01-30', 'gender' => 'female', 'address' => 'Calle Las Flores 321, Lima', 'emergency_contact_name' => 'Luis Rodríguez', 'emergency_contact_phone' => '+51 987 123 463', 'medical_history' => 'Sin antecedentes', 'notes' => 'Limpiezas regulares'],
            ['first_name' => 'Luis', 'last_name' => 'Fernández', 'email' => 'luis.fernandez@email.com', 'phone' => '+51 987 123 464', 'birth_date' => '1982-09-12', 'gender' => 'male', 'address' => 'Av. Universitaria 654, Lima', 'emergency_contact_name' => 'Carmen Fernández', 'emergency_contact_phone' => '+51 987 123 465', 'medical_history' => 'Asma leve', 'notes' => 'Implantes dentales'],
            ['first_name' => 'Carmen', 'last_name' => 'Vargas', 'email' => 'carmen.vargas@email.com', 'phone' => '+51 987 123 466', 'birth_date' => '1988-05-18', 'gender' => 'female', 'address' => 'Jr. San Martín 987, Lima', 'emergency_contact_name' => 'Roberto Vargas', 'emergency_contact_phone' => '+51 987 123 467', 'medical_history' => 'Embarazo (6 meses)', 'notes' => 'Control prenatal dental'],
            ['first_name' => 'Roberto', 'last_name' => 'Herrera', 'email' => 'roberto.herrera@email.com', 'phone' => '+51 987 123 468', 'birth_date' => '1975-12-03', 'gender' => 'male', 'address' => 'Av. Arequipa 147, Lima', 'emergency_contact_name' => 'Patricia Herrera', 'emergency_contact_phone' => '+51 987 123 469', 'medical_history' => 'Hipercolesterolemia', 'notes' => 'Prótesis parcial'],
            ['first_name' => 'Patricia', 'last_name' => 'Morales', 'email' => 'patricia.morales@email.com', 'phone' => '+51 987 123 470', 'birth_date' => '1993-08-25', 'gender' => 'female', 'address' => 'Calle Los Pinos 258, Lima', 'emergency_contact_name' => 'Diego Morales', 'emergency_contact_phone' => '+51 987 123 471', 'medical_history' => 'Sin antecedentes', 'notes' => 'Brackets metálicos'],
            ['first_name' => 'Diego', 'last_name' => 'Castro', 'email' => 'diego.castro@email.com', 'phone' => '+51 987 123 472', 'birth_date' => '1987-04-14', 'gender' => 'male', 'address' => 'Av. Javier Prado 369, Lima', 'emergency_contact_name' => 'Sofia Castro', 'emergency_contact_phone' => '+51 987 123 473', 'medical_history' => 'Alergia a penicilina', 'notes' => 'Endodoncia múltiple'],
            ['first_name' => 'Sofia', 'last_name' => 'Jiménez', 'email' => 'sofia.jimenez@email.com', 'phone' => '+51 987 123 474', 'birth_date' => '1991-10-07', 'gender' => 'female', 'address' => 'Jr. Tacna 741, Lima', 'emergency_contact_name' => 'Miguel Jiménez', 'emergency_contact_phone' => '+51 987 123 475', 'medical_history' => 'Sin antecedentes', 'notes' => 'Limpiezas cada 6 meses']
        ];

        foreach ($patients as $patient) {
            DB::table('patients')->insert([
                'first_name' => $patient['first_name'],
                'last_name' => $patient['last_name'],
                'email' => $patient['email'],
                'phone' => $patient['phone'],
                'birth_date' => $patient['birth_date'],
                'gender' => $patient['gender'],
                'address' => $patient['address'],
                'emergency_contact_name' => $patient['emergency_contact_name'],
                'emergency_contact_phone' => $patient['emergency_contact_phone'],
                'medical_history' => $patient['medical_history'],
                'notes' => $patient['notes'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create appointments
        $appointmentTypes = DB::table('appointment_types')->get();
        $dentalChairs = DB::table('dental_chairs')->get();
        $professionals = DB::table('users')->where('role', 'odontologo')->get();
        $patients = DB::table('patients')->get();

        $appointments = [];
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now()->addDays(30);

        // Generate appointments for the last 30 days and next 30 days
        for ($i = 0; $i < 100; $i++) {
            $appointmentDate = $startDate->copy()->addDays(rand(0, 60));
            $appointmentTime = Carbon::createFromTime(rand(8, 17), rand(0, 3) * 15, 0);

            // Skip weekends
            if ($appointmentDate->isWeekend()) {
                continue;
            }

            $scheduledAt = $appointmentDate->copy()->setTime($appointmentTime->hour, $appointmentTime->minute, 0);
            $duration = $appointmentTypes->random()->default_duration_minutes;
            $endsAt = $scheduledAt->copy()->addMinutes($duration);

            $appointments[] = [
                'user_id' => $professionals->random()->id,
                'patient_id' => $patients->random()->id,
                'dental_chair_id' => $dentalChairs->random()->id,
                'appointment_type_id' => $appointmentTypes->random()->id,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $duration,
                'status' => ['scheduled', 'confirmed', 'completed', 'cancelled'][rand(0, 3)],
                'notes' => 'Cita generada automáticamente para pruebas',
                'created_by' => 1, // Admin user
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Insert appointments in batches to avoid conflicts
        $chunks = array_chunk($appointments, 20);
        foreach ($chunks as $chunk) {
            DB::table('appointments')->insert($chunk);
        }

        $this->command->info('Sample data created successfully!');
        $this->command->info('- 6 Appointment Types');
        $this->command->info('- 5 Dental Chairs');
        $this->command->info('- 5 Professionals');
        $this->command->info('- 10 Patients');
        $this->command->info('- 100 Appointments');
    }
}
