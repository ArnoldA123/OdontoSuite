<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DentalChair;

class DentalChairSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dentalChairs = [
            [
                'name' => 'Silla 1',
                'code' => 'SILLA-001',
                'description' => 'Silla dental principal - Consultorio 1',
                'is_active' => true,
            ],
            [
                'name' => 'Silla 2',
                'code' => 'SILLA-002',
                'description' => 'Silla dental secundaria - Consultorio 2',
                'is_active' => true,
            ],
            [
                'name' => 'Silla 3',
                'code' => 'SILLA-003',
                'description' => 'Silla dental para cirugías - Consultorio 3',
                'is_active' => true,
            ],
            [
                'name' => 'Silla 4',
                'code' => 'SILLA-004',
                'description' => 'Silla dental para ortodoncia - Consultorio 4',
                'is_active' => true,
            ],
            [
                'name' => 'Silla 5',
                'code' => 'SILLA-005',
                'description' => 'Silla dental de emergencias - Consultorio 5',
                'is_active' => true,
            ],
        ];

        foreach ($dentalChairs as $chair) {
            DentalChair::create($chair);
        }
    }
}
