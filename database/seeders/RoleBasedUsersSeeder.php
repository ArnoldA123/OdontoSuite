<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleBasedUsersSeeder extends Seeder
{
    /**
     * Sede de prueba del dataset (citas-privacidad T3): todos los usuarios
     * sembrados se afilian a Trujillo para que el filtro de sede tenga una
     * poblacion real que medir.
     */
    private const TEST_BRANCH_CODE = 'SC-TRU-01';

    /**
     * Unico usuario que conserva alcance de grupo (sin sede): es la cuenta con
     * la que se sigue administrando el catalogo de sedes (ADR-0010).
     */
    private const GROUP_SCOPE_EMAIL = 'admin+administrador@test.com';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            // Administradores: uno por sede y el de grupo (sin sede).
            [
                'name' => 'Elizabet Cunia Cruz',
                'username' => 'elizabet',
                'email' => 'elizabet+administrador@test.com',
                'phone' => '+51 987 654 001',
                'password' => Hash::make('password123'),
                'role' => 'administrador',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Gerencia OdontoSuite',
                'username' => 'admin_test',
                'email' => 'admin+administrador@test.com',
                'phone' => '+51 987 654 003',
                'password' => Hash::make('password123'),
                'role' => 'administrador',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Recepcionista
            [
                'name' => 'Rosa Quispe Flores',
                'username' => 'recepcionista_test',
                'email' => 'recepcionista+recepcionista@test.com',
                'phone' => '+51 987 654 004',
                'password' => Hash::make('password123'),
                'role' => 'recepcionista',
                'specialty' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Odontólogos
            [
                'name' => 'Ever Huamán Cruz',
                'username' => 'ever_odontologo',
                'email' => 'ever+odontologo@test.com',
                'phone' => '+51 987 654 005',
                'password' => Hash::make('password123'),
                'role' => 'odontologo',
                'specialty' => 'general',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Brenda Tejada Alfaro',
                'username' => 'brenda',
                'email' => 'brenda+odontologo@test.com',
                'phone' => '+51 987 654 006',
                'password' => Hash::make('password123'),
                'role' => 'odontologo',
                'specialty' => 'orthodontics',
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Implantólogo
            [
                'name' => 'Wilmer Valderrama',
                'username' => 'wilmer',
                'email' => 'wilmer+implantologo@test.com',
                'phone' => '+51 987 654 008',
                'password' => Hash::make('password123'),
                'role' => 'implantologo',
                'specialty' => 'implantology',
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Técnico Dental
            [
                'name' => 'Sofia Villanueva',
                'username' => 'sofia',
                'email' => 'sofia+tecnico@test.com',
                'phone' => '+51 987 654 010',
                'password' => Hash::make('password123'),
                'role' => 'tecnico_dental',
                'specialty' => 'dental_technician',
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Asistente
            [
                'name' => 'Azul Huamán Díaz',
                'username' => 'azul',
                'email' => 'azul+asistente@test.com',
                'phone' => '+51 987 654 012',
                'password' => Hash::make('password123'),
                'role' => 'asistente',
                'specialty' => 'assistant',
                'is_active' => true,
                'email_verified_at' => now(),
            ],

            // Finanzas
            [
                'name' => 'Milagros Cochachin',
                'username' => 'milagros',
                'email' => 'milagros+finanzas@test.com',
                'phone' => '+51 987 654 014',
                'password' => Hash::make('password123'),
                'role' => 'finanzas',
                'specialty' => 'finance',
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

        $this->assignTestBranch();

        $this->command->info('Usuarios con roles creados exitosamente.');
    }

    /**
     * Afilia a Trujillo los usuarios sembrados que todavia no tienen sede.
     *
     * Una sede ya asignada (la del seeder o la que el administrador fijo a
     * mano) nunca se pisa, y el administrador de grupo se queda sin sede a
     * proposito. Si el seeder corre solo contra una base sin Trujillo no se
     * inventa la fila: los usuarios quedan sin sede y se avisa por consola.
     */
    private function assignTestBranch(): void
    {
        $branchId = Branch::query()
            ->where('code', self::TEST_BRANCH_CODE)
            ->value('id');

        if (! $branchId) {
            $this->command?->warn(
                'No existe la sede '.self::TEST_BRANCH_CODE
                .' (Trujillo San Isidro): los usuarios sembrados quedan sin sede.'
            );

            return;
        }

        User::query()
            ->whereNull('branch_id')
            ->where('email', '!=', self::GROUP_SCOPE_EMAIL)
            ->update(['branch_id' => $branchId]);
    }
}
