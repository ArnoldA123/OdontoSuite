<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario administrador de prueba
        User::create([
            'name' => 'Dr. Juan Pérez',
            'email' => 'admin@easydent.com',
            'username' => 'admin',
            'password' => Hash::make('password123'),
            'role' => 'administrador',
            'is_active' => true,
            'specialty' => 'Odontología General',
            'phone' => '+51 987 654 321'
        ]);

        // Crear usuario odontólogo de prueba
        User::create([
            'name' => 'Dra. María García',
            'email' => 'odontologo@easydent.com',
            'username' => 'odontologo',
            'password' => Hash::make('password123'),
            'role' => 'odontologo',
            'is_active' => true,
            'specialty' => 'Ortodoncia',
            'phone' => '+51 987 654 322'
        ]);

        // Crear usuario recepcionista de prueba
        User::create([
            'name' => 'Ana López',
            'email' => 'recepcionista@easydent.com',
            'username' => 'recepcionista',
            'password' => Hash::make('password123'),
            'role' => 'recepcionista',
            'is_active' => true,
            'phone' => '+51 987 654 323'
        ]);

        $this->command->info('Usuarios de prueba creados exitosamente:');
        $this->command->info('Admin: admin@easydent.com / password123');
        $this->command->info('Odontólogo: odontologo@easydent.com / password123');
        $this->command->info('Recepcionista: recepcionista@easydent.com / password123');
    }
}