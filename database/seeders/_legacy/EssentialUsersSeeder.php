<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EssentialUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Crea solo los usuarios mínimos esenciales para que el sistema funcione.
     * Usuarios genéricos sin datos personales reales.
     */
    public function run(): void
    {
        $users = [
            // Administrador principal
            [
                'name' => 'Administrador',
                'username' => 'admin',
                'email' => 'admin@odontosuite.com',
                'phone' => null,
                'password' => Hash::make('password'),
                'role' => 'administrador',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            // Recepcionista
            [
                'name' => 'Recepcionista',
                'username' => 'recepcionista',
                'email' => 'recepcionista@odontosuite.com',
                'phone' => null,
                'password' => Hash::make('password'),
                'role' => 'recepcionista',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            // Odontólogo
            [
                'name' => 'Odontólogo',
                'username' => 'odontologo',
                'email' => 'odontologo@odontosuite.com',
                'phone' => null,
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'general',
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

        $this->command->info('Usuarios esenciales creados exitosamente.');
        $this->command->info('Credenciales por defecto: email = [rol]@odontosuite.com, password = password');
    }
}
