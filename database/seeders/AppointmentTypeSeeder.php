<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AppointmentType;

class AppointmentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $appointmentTypes = [
            [
                'name' => 'Consulta General',
                'description' => 'Consulta dental de rutina y revisión general',
                'default_duration_minutes' => 30,
                'price' => 50.00,
                'color' => '#3B82F6',
                'requires_confirmation' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Limpieza Dental',
                'description' => 'Profilaxis y limpieza dental profesional',
                'default_duration_minutes' => 45,
                'price' => 80.00,
                'color' => '#10B981',
                'requires_confirmation' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Extracción',
                'description' => 'Extracción de diente o muela',
                'default_duration_minutes' => 60,
                'price' => 120.00,
                'color' => '#EF4444',
                'requires_confirmation' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Empaste',
                'description' => 'Restauración con resina o amalgama',
                'default_duration_minutes' => 45,
                'price' => 90.00,
                'color' => '#F59E0B',
                'requires_confirmation' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Endodoncia',
                'description' => 'Tratamiento de conducto',
                'default_duration_minutes' => 120,
                'price' => 300.00,
                'color' => '#8B5CF6',
                'requires_confirmation' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Corona',
                'description' => 'Colocación de corona dental',
                'default_duration_minutes' => 90,
                'price' => 400.00,
                'color' => '#06B6D4',
                'requires_confirmation' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Ortodoncia',
                'description' => 'Consulta y ajuste de ortodoncia',
                'default_duration_minutes' => 30,
                'price' => 60.00,
                'color' => '#84CC16',
                'requires_confirmation' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Cirugía Oral',
                'description' => 'Procedimientos quirúrgicos orales',
                'default_duration_minutes' => 180,
                'price' => 500.00,
                'color' => '#DC2626',
                'requires_confirmation' => true,
                'is_active' => true,
            ],
        ];

        foreach ($appointmentTypes as $type) {
            AppointmentType::firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
