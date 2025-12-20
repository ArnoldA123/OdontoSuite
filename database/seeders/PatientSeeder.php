<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Patient;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nombres y apellidos peruanos realistas
        $firstNames = [
            'Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Carmen', 'José', 'Rosa', 'Miguel', 'Elena',
            'Pedro', 'Lucía', 'Antonio', 'Isabel', 'Francisco', 'Patricia', 'Manuel', 'Sandra', 'David', 'Mónica',
            'Roberto', 'Claudia', 'Fernando', 'Verónica', 'Ricardo', 'Gabriela', 'Alejandro', 'Natalia', 'Diego', 'Valeria',
            'Andrés', 'Camila', 'Sebastián', 'Sofía', 'Daniel', 'Paola', 'Gonzalo', 'Andrea', 'Martín', 'Carolina',
            'Felipe', 'Daniela', 'Ignacio', 'Constanza', 'Rodrigo', 'Francisca', 'Matías', 'Javiera', 'Nicolás', 'Catalina',
            'Santiago', 'Amanda', 'Emilio', 'Antonella', 'Benjamín', 'Isidora', 'Vicente', 'Trinidad', 'Tomás', 'Emilia',
            'Agustín', 'Josefa', 'Maximiliano', 'Magdalena', 'Cristóbal', 'Pascale', 'Joaquín', 'Ignacia', 'Simón', 'Rafaela',
            'Lucas', 'María José', 'Gabriel', 'María Paz', 'Alonso', 'María Jesús', 'Bastián', 'María Ignacia', 'Vicente', 'María Elena',
            'Javier', 'María Fernanda', 'Pablo', 'María Victoria', 'Rafael', 'María Soledad', 'Eduardo', 'María Angélica', 'Hernán', 'María Teresa',
            'Cristian', 'María del Carmen', 'Patricio', 'María Inés', 'Fabián', 'María Loreto', 'Gonzalo', 'María Consuelo', 'Hugo', 'María Esperanza'
        ];

        $lastNames = [
            'García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez', 'Pérez', 'Gómez', 'Martín',
            'Jiménez', 'Ruiz', 'Hernández', 'Díaz', 'Moreno', 'Muñoz', 'Álvarez', 'Romero', 'Alonso', 'Gutiérrez',
            'Navarro', 'Torres', 'Domínguez', 'Vázquez', 'Ramos', 'Gil', 'Ramírez', 'Serrano', 'Blanco', 'Suárez',
            'Molina', 'Morales', 'Ortega', 'Delgado', 'Castro', 'Ortiz', 'Rubio', 'Marín', 'Sanz', 'Iglesias',
            'Medina', 'Cortés', 'Castillo', 'Garrido', 'Santos', 'Lozano', 'Guerrero', 'Cano', 'Prieto', 'Méndez',
            'Cruz', 'Calvo', 'Gallego', 'Vidal', 'León', 'Herrera', 'Márquez', 'Peña', 'Flores', 'Cabrera',
            'Campos', 'Vega', 'Fuentes', 'Carrasco', 'Diez', 'Caballero', 'Reyes', 'Nieto', 'Aguilar', 'Pascual',
            'Santana', 'Herrero', 'Montero', 'Lara', 'Hidalgo', 'Giménez', 'Ibáñez', 'Ferrer', 'Duran', 'Santiago',
            'Benítez', 'Vargas', 'Mora', 'Vicente', 'Arias', 'Carmona', 'Crespo', 'Román', 'Pastor', 'Soto',
            'Sáez', 'Velasco', 'Moya', 'Soler', 'Parra', 'Esteban', 'Bravo', 'Gallardo', 'Rojas', 'Pardo'
        ];

        $addresses = [
            'Av. Larco 123, Miraflores, Trujillo',
            'Jr. Pizarro 456, Centro Histórico, Trujillo',
            'Av. España 789, La Esperanza, Trujillo',
            'Calle Los Olivos 321, El Porvenir, Trujillo',
            'Av. América Norte 654, Trujillo',
            'Jr. San Martín 987, Trujillo',
            'Av. Juan Pablo II 147, Trujillo',
            'Calle Grau 258, Trujillo',
            'Av. Túpac Amaru 369, Trujillo',
            'Jr. Bolívar 741, Trujillo',
            'Av. Mansiche 852, Trujillo',
            'Calle Ayacucho 963, Trujillo',
            'Av. Húsares de Junín 159, Trujillo',
            'Jr. Independencia 357, Trujillo',
            'Av. Los Incas 468, Trujillo',
            'Calle San Agustín 579, Trujillo',
            'Av. Nicolás de Piérola 680, Trujillo',
            'Jr. Orbegoso 791, Trujillo',
            'Av. Larco Herrera 802, Trujillo',
            'Calle Almagro 913, Trujillo'
        ];

        $medicalHistories = [
            'Ninguna',
            'Hipertensión arterial',
            'Diabetes tipo 2',
            'Asma',
            'Alergia a medicamentos',
            'Problemas cardíacos',
            'Artritis',
            'Migrañas',
            'Depresión',
            'Ansiedad',
            'Problemas de tiroides',
            'Colesterol alto',
            'Gastritis',
            'Problemas renales',
            'Epilepsia'
        ];

        $allergies = [
            'Ninguna',
            'Penicilina',
            'Látex',
            'Polen',
            'Ácaros',
            'Mariscos',
            'Frutos secos',
            'Lactosa',
            'Gluten',
            'Sulfitos',
            'Anestésicos locales',
            'Yodo',
            'Metales (níquel)',
            'Fragancias',
            'Conservantes'
        ];

        $notes = [
            'Paciente regular, viene cada 6 meses',
            'Paciente nuevo, primera consulta',
            'Requiere atención especial',
            'Paciente joven, ortodoncia',
            'Paciente con asma, evitar estrés',
            'Paciente diabético, control especial',
            'Paciente hipertenso, monitoreo',
            'Paciente con alergias, precaución',
            'Paciente embarazada, cuidados especiales',
            'Paciente de tercera edad, atención geriátrica',
            'Paciente con discapacidad, adaptaciones',
            'Paciente con fobia dental, sedación',
            'Paciente con prótesis, mantenimiento',
            'Paciente post-operatorio, seguimiento',
            'Paciente con implantes, control periódico'
        ];

        $patients = [];

        for ($i = 1; $i <= 100; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $gender = rand(0, 1) ? 'male' : 'female';
            $birthYear = rand(1945, 2020);
            $birthMonth = rand(1, 12);
            $birthDay = rand(1, 28);
            $birthDate = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);

            // Generar email variado
            $emailDomains = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com'];
            $email = strtolower($firstName . '.' . $lastName . '@' . $emailDomains[array_rand($emailDomains)]);

            // Generar teléfono celular peruano
            $phonePrefix = ['987', '986', '985', '984', '983', '982', '981', '980'];
            $phone = '+51 ' . $phonePrefix[array_rand($phonePrefix)] . ' ' . sprintf('%03d %03d', rand(100, 999), rand(100, 999));

            // Generar contacto de emergencia
            $emergencyName = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $emergencyPhone = '+51 ' . $phonePrefix[array_rand($phonePrefix)] . ' ' . sprintf('%03d %03d', rand(100, 999), rand(100, 999));

            $patients[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'document_number' => 'DOC-' . str_pad($i + 1, 8, '0', STR_PAD_LEFT),
                'email' => $email,
                'phone' => $phone,
                'birth_date' => $birthDate,
                'gender' => $gender,
                'address' => $addresses[array_rand($addresses)],
                'emergency_contact_name' => $emergencyName,
                'emergency_contact_phone' => $emergencyPhone,
                'medical_history' => $medicalHistories[array_rand($medicalHistories)],
                'allergies' => $allergies[array_rand($allergies)],
                'notes' => $notes[array_rand($notes)],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($patients as $patient) {
            Patient::create($patient);
        }

        $this->command->info('100 pacientes creados exitosamente.');
    }
}
