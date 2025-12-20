<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleBasedUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            // Administradores
            [
                'name' => 'Elizabet Cunia Cruz',
                'username' => 'elizabet',
                'email' => 'elizabet+administrador@test.com',
                'phone' => '+51 987 654 001',
                'password' => Hash::make('password123'),
                'role' => 'administrador',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Ever Huamán Cruz',
                'username' => 'ever',
                'email' => 'ever+administrador@test.com',
                'phone' => '+51 987 654 002',
                'password' => Hash::make('password123'),
                'role' => 'administrador',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Admin Test',
                'username' => 'admin_test',
                'email' => 'admin+administrador@test.com',
                'phone' => '+51 987 654 003',
                'password' => Hash::make('password123'),
                'role' => 'administrador',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Recepcionista
            [
                'name' => 'Recepcionista Test',
                'username' => 'recepcionista_test',
                'email' => 'recepcionista+recepcionista@test.com',
                'phone' => '+51 987 654 004',
                'password' => Hash::make('password123'),
                'role' => 'recepcionista',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Odontólogos
            [
                'name' => 'Ever Huamán Cruz',
                'username' => 'ever_odontologo',
                'email' => 'ever+odontologo@test.com',
                'phone' => '+51 987 654 005',
                'password' => Hash::make('password123'),
                'role' => 'odontologo',
                'specialty' => 'general',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Brenda Tejada Alfaro',
                'username' => 'brenda',
                'email' => 'brenda+odontologo@test.com',
                'phone' => '+51 987 654 006',
                'password' => Hash::make('password123'),
                'role' => 'odontologo',
                'specialty' => 'orthodontics',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Odontologo Test',
                'username' => 'odontologo_test',
                'email' => 'odontologo+odontologo@test.com',
                'phone' => '+51 987 654 007',
                'password' => Hash::make('password123'),
                'role' => 'odontologo',
                'specialty' => 'general',
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Implantólogos
            [
                'name' => 'Wilmer Valderrama',
                'username' => 'wilmer',
                'email' => 'wilmer+implantologo@test.com',
                'phone' => '+51 987 654 008',
                'password' => Hash::make('password123'),
                'role' => 'implantologo',
                'specialty' => 'implantology',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Implantologo Test',
                'username' => 'implantologo_test',
                'email' => 'implantologo+implantologo@test.com',
                'phone' => '+51 987 654 009',
                'password' => Hash::make('password123'),
                'role' => 'implantologo',
                'specialty' => 'implantology',
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Técnicos Dentales
            [
                'name' => 'Sofia Villanueva',
                'username' => 'sofia',
                'email' => 'sofia+tecnico@test.com',
                'phone' => '+51 987 654 010',
                'password' => Hash::make('password123'),
                'role' => 'tecnico_dental',
                'specialty' => 'dental_technician',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Tecnico Test',
                'username' => 'tecnico_test',
                'email' => 'tecnico+tecnico@test.com',
                'phone' => '+51 987 654 011',
                'password' => Hash::make('password123'),
                'role' => 'tecnico_dental',
                'specialty' => 'dental_technician',
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Asistentes
            [
                'name' => 'Azul Huamán Díaz',
                'username' => 'azul',
                'email' => 'azul+asistente@test.com',
                'phone' => '+51 987 654 012',
                'password' => Hash::make('password123'),
                'role' => 'asistente',
                'specialty' => 'assistant',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Asistente Test',
                'username' => 'asistente_test',
                'email' => 'asistente+asistente@test.com',
                'phone' => '+51 987 654 013',
                'password' => Hash::make('password123'),
                'role' => 'asistente',
                'specialty' => 'assistant',
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Finanzas
            [
                'name' => 'Milagros Cochachin',
                'username' => 'milagros',
                'email' => 'milagros+finanzas@test.com',
                'phone' => '+51 987 654 014',
                'password' => Hash::make('password123'),
                'role' => 'finanzas',
                'specialty' => 'finance',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Finanzas Test',
                'username' => 'finanzas_test',
                'email' => 'finanzas+finanzas@test.com',
                'phone' => '+51 987 654 015',
                'password' => Hash::make('password123'),
                'role' => 'finanzas',
                'specialty' => 'finance',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Usuarios con roles creados exitosamente.');
    }
}



































