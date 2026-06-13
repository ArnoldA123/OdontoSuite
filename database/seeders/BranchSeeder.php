<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

/**
 * Sprint 0 (B-CASH-1): poblar tabla branches con 3 sedes base.
 * Patron updateOrCreate por `code` para idempotencia: re-ejecutar el seeder
 * (o un migrate:fresh --seed) no duplica filas, solo actualiza los campos
 * que cambiaron.
 *
 * Las 3 sedes son las mas comunes en Peru: sede central historica + 2
 * sedes de expansion. El admin puede crear mas desde la UI (Sprint 1).
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'code' => 'SC-LIM-01',
                'name' => 'Sede Central Lima',
                'address' => 'Jr. de la Union 123, Cercado de Lima',
                'city' => 'Lima',
                'state' => 'Lima',
                'country' => 'Peru',
                'phone' => '+51 1 426-0001',
                'email' => 'central@odontosuite.pe',
                'timezone' => 'America/Lima',
                'description' => 'Sede principal con atencion general y especializada.',
                'is_active' => true,
            ],
            [
                'code' => 'SC-NOR-01',
                'name' => 'Sede Norte',
                'address' => 'Av. Tupac Amaru 456, Los Olivos',
                'city' => 'Lima',
                'state' => 'Lima',
                'country' => 'Peru',
                'phone' => '+51 1 528-0002',
                'email' => 'norte@odontosuite.pe',
                'timezone' => 'America/Lima',
                'description' => 'Sede de expansion zona norte.',
                'is_active' => true,
            ],
            [
                'code' => 'SC-SUR-01',
                'name' => 'Sede Sur (Surco)',
                'address' => 'Av. Benavides 789, Santiago de Surco',
                'city' => 'Lima',
                'state' => 'Lima',
                'country' => 'Peru',
                'phone' => '+51 1 612-0003',
                'email' => 'sur@odontosuite.pe',
                'timezone' => 'America/Lima',
                'description' => 'Sede zona sur, foco en ortodoncia y estetica.',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $data) {
            Branch::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
