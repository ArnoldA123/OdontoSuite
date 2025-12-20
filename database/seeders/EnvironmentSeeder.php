<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DentalChair;

class EnvironmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $environments = [
            [
                'name' => 'Ambiente 1',
                'code' => 'AMB-001',
                'description' => 'Ambiente principal con equipamiento completo para odontología general',
                'equipment' => 'Silla dental, lámpara LED, compresor, unidad de rayos X, autoclave',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'name' => 'Ambiente 2',
                'code' => 'AMB-002',
                'description' => 'Ambiente especializado para ortodoncia y tratamientos estéticos',
                'equipment' => 'Silla dental, lámpara LED, compresor, unidad de rayos X, autoclave, lámpara de fotocurado',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'name' => 'Ambiente 3',
                'code' => 'AMB-003',
                'description' => 'Ambiente para endodoncia y cirugía oral',
                'equipment' => 'Silla dental, lámpara LED, compresor, unidad de rayos X, autoclave, microscopio dental',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'name' => 'Ambiente 4',
                'code' => 'AMB-004',
                'description' => 'Ambiente para periodoncia y limpieza dental',
                'equipment' => 'Silla dental, lámpara LED, compresor, unidad de rayos X, autoclave, equipo de ultrasonido',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'name' => 'Ambiente 5',
                'code' => 'AMB-005',
                'description' => 'Ambiente para odontopediatría',
                'equipment' => 'Silla dental, lámpara LED, compresor, unidad de rayos X, autoclave, equipo de sedación',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'name' => 'Ambiente 6',
                'code' => 'AMB-006',
                'description' => 'Ambiente para prótesis dental',
                'equipment' => 'Silla dental, lámpara LED, compresor, unidad de rayos X, autoclave, laboratorio dental',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'name' => 'Ambiente 7',
                'code' => 'AMB-007',
                'description' => 'Ambiente de emergencia y consultas rápidas',
                'equipment' => 'Silla dental, lámpara LED, compresor, unidad de rayos X, autoclave',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'name' => 'Ambiente 8',
                'code' => 'AMB-008',
                'description' => 'Ambiente en mantenimiento',
                'equipment' => 'Silla dental, lámpara LED, compresor, unidad de rayos X, autoclave',
                'status' => 'maintenance',
                'is_active' => false,
            ],
        ];

        foreach ($environments as $environment) {
            DentalChair::firstOrCreate(
                ['code' => $environment['code']],
                $environment
            );
        }
    }
}
