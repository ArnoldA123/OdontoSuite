<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfessionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $professionals = [
            [
                'name' => 'Dr. Carlos García',
                'username' => 'dr.garcia',
                'email' => 'dr.garcia@easydent.com',
                'phone' => '+51 987 654 321',
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'general',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dra. Ana Martínez',
                'username' => 'dra.martinez',
                'email' => 'dra.martinez@easydent.com',
                'phone' => '+51 987 654 322',
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'orthodontics',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Luis Rodríguez',
                'username' => 'dr.rodriguez',
                'email' => 'dr.rodriguez@easydent.com',
                'phone' => '+51 987 654 323',
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'endodontics',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dra. María López',
                'username' => 'dra.lopez',
                'email' => 'dra.lopez@easydent.com',
                'phone' => '+51 987 654 324',
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'periodontics',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Pedro Sánchez',
                'username' => 'dr.sanchez',
                'email' => 'dr.sanchez@easydent.com',
                'phone' => '+51 987 654 325',
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'oral_surgery',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dra. Carmen Flores',
                'username' => 'dra.flores',
                'email' => 'dra.flores@easydent.com',
                'phone' => '+51 987 654 326',
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'pediatric',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Roberto Torres',
                'username' => 'dr.torres',
                'email' => 'dr.torres@easydent.com',
                'phone' => '+51 987 654 327',
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'prosthodontics',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dra. Elena Vargas',
                'username' => 'dra.vargas',
                'email' => 'dra.vargas@easydent.com',
                'phone' => '+51 987 654 328',
                'password' => Hash::make('password'),
                'role' => 'odontologo',
                'specialty' => 'cosmetic',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($professionals as $professional) {
            User::firstOrCreate(
                ['email' => $professional['email']],
                $professional
            );
        }
    }
}
