<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DentistUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dentists = [
            [
                'name' => 'Dr. Carlos Mendoza',
                'email' => 'carlos.mendoza@easydent.com',
                'username' => 'dr.carlos',
                'password' => Hash::make('dentista123'),
                'role' => 'odontologo',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dra. Ana García',
                'email' => 'ana.garcia@easydent.com',
                'username' => 'dra.ana',
                'password' => Hash::make('dentista123'),
                'role' => 'odontologo',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dr. Luis Rodríguez',
                'email' => 'luis.rodriguez@easydent.com',
                'username' => 'dr.luis',
                'password' => Hash::make('dentista123'),
                'role' => 'odontologo',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Dra. María López',
                'email' => 'maria.lopez@easydent.com',
                'username' => 'dra.maria',
                'password' => Hash::make('dentista123'),
                'role' => 'odontologo',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($dentists as $dentist) {
            User::create($dentist);
        }
    }
}
