<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::firstOrCreate(
            ['code' => 'SP001'],
            [
            'name' => 'Sede Principal',
            'code' => 'SP001',
            'address' => 'Av. Principal 123, Lima, Perú',
            'phone' => '+51 1 234-5678',
            'email' => 'principal@odontosuite.com',
            'city' => 'Lima',
            'state' => 'Lima',
            'country' => 'Perú',
            'postal_code' => '15001',
            'timezone' => 'America/Lima',
            'latitude' => -12.0464,
            'longitude' => -77.0428,
            'description' => 'Sede principal de OdontoSuite',
            'settings' => [
                'working_hours' => [
                    'monday' => ['start' => '08:00', 'end' => '18:00'],
                    'tuesday' => ['start' => '08:00', 'end' => '18:00'],
                    'wednesday' => ['start' => '08:00', 'end' => '18:00'],
                    'thursday' => ['start' => '08:00', 'end' => '18:00'],
                    'friday' => ['start' => '08:00', 'end' => '18:00'],
                    'saturday' => ['start' => '09:00', 'end' => '14:00'],
                    'sunday' => ['start' => '09:00', 'end' => '12:00']
                ],
                'appointment_duration' => 30,
                'currency' => 'PEN',
                'tax_rate' => 18.0
            ],
            'is_active' => true
            ]
        );
    }
}
