<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DentalChair;
use Illuminate\Database\Seeder;

class EnvironmentSeeder extends Seeder
{
    /**
     * Sede de cada ambiente por su codigo (citas-privacidad T3).
     *
     * Trujillo concentra los ambientes operativos porque alli viven los
     * usuarios y las citas sembradas; Lima, Norte y Sur conservan uno cada una
     * para que el campo y el filtro de sede se puedan probar. Se resuelve por
     * `code`, nunca por id: las filas de `branches` pueden variar entre bases.
     */
    private const BRANCH_CODES = [
        'AMB-001' => 'SC-TRU-01',
        'AMB-002' => 'SC-TRU-01',
        'AMB-003' => 'SC-TRU-01',
        'AMB-004' => 'SC-LIM-01',
        'AMB-005' => 'SC-NOR-01',
        'AMB-006' => 'SC-SUR-01',
        'AMB-007' => 'SC-TRU-01',
        'AMB-008' => 'SC-TRU-01',
    ];

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

        $branchIds = Branch::query()
            ->whereIn('code', array_values(array_unique(self::BRANCH_CODES)))
            ->pluck('id', 'code');

        $missing = [];

        foreach ($environments as $environment) {
            $data = $environment;
            $branchCode = self::BRANCH_CODES[$environment['code']] ?? null;

            if ($branchCode && isset($branchIds[$branchCode])) {
                $data['branch_id'] = $branchIds[$branchCode];
            } elseif ($branchCode) {
                // Sin la sede no se borra una asignacion hecha a mano: se omite
                // la columna del payload y se avisa por consola.
                $missing[$branchCode] = true;
            }

            DentalChair::updateOrCreate(
                ['code' => $environment['code']],
                $data
            );
        }

        if ($missing !== []) {
            $this->command?->warn(
                'Faltan sedes del catalogo ('.implode(', ', array_keys($missing))
                .'): los ambientes afectados conservan su sede actual.'
            );
        }
    }
}
