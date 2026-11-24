<?php

namespace Tests\Feature\Console;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\RoleBasedUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Demo cleanup: six "Test" accounts (and the duplicated Ever administrator)
 * leave the dataset, one account per role stays, and the clinical demo that
 * hangs from those accounts survives.
 *
 * The command reassigns every business record before deleting the user, so the
 * 100+ patients, their appointments, the cash ledger and the clinical history
 * are not taken down by a CASCADE. These tests pin that contract, the dry-run
 * (which writes nothing), idempotency, and the abort that leaves the base
 * untouched when a slot collision would make the reassignment unsafe.
 */
class PruneDemoTestUsersTest extends TestCase
{
    use RefreshDatabase;

    private const BRANCH_CODE = 'SC-TRU-01';

    private const GROUP_EMAIL = 'admin+administrador@test.com';

    private const RECEPTION_EMAIL = 'recepcionista+recepcionista@test.com';

    /** @var list<string> */
    private const PRUNED_EMAILS = [
        'ever+administrador@test.com',
        'odontologo+odontologo@test.com',
        'implantologo+implantologo@test.com',
        'tecnico+tecnico@test.com',
        'asistente+asistente@test.com',
        'finanzas+finanzas@test.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // A previous migrate:fresh --seed run survives the RefreshDatabase
        // transaction; purge it so the population measured here is the one this
        // test builds.
        DB::table('users')->delete();

        $this->seed(BranchSeeder::class);
        $this->seed(RoleBasedUsersSeeder::class);
        $this->createPrunedAccounts();
    }

    private function branch(): Branch
    {
        return Branch::query()->where('code', self::BRANCH_CODE)->firstOrFail();
    }

    private function createPrunedAccounts(): void
    {
        $accounts = [
            ['Ever Huamán Cruz', 'ever', 'ever+administrador@test.com', 'administrador'],
            ['Odontologo Test', 'odontologo_test', 'odontologo+odontologo@test.com', 'odontologo'],
            ['Implantologo Test', 'implantologo_test', 'implantologo+implantologo@test.com', 'implantologo'],
            ['Tecnico Test', 'tecnico_test', 'tecnico+tecnico@test.com', 'tecnico_dental'],
            ['Asistente Test', 'asistente_test', 'asistente+asistente@test.com', 'asistente'],
            ['Finanzas Test', 'finanzas_test', 'finanzas+finanzas@test.com', 'finanzas'],
        ];

        foreach ($accounts as [$name, $username, $email, $role]) {
            User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'phone' => '+51 987 654 999',
                'password' => 'password123',
                'role' => $role,
                'is_active' => true,
                'branch_id' => $this->branch()->id,
                'email_verified_at' => now(),
            ]);
        }
    }

    private function userByEmail(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function appointmentFor(User $professional, User $creator, string $suffix = '1'): Appointment
    {
        return Appointment::factory()->create([
            'user_id' => $professional->id,
            'created_by' => $creator->id,
            'patient_id' => Patient::factory()->create(['document_number' => "DOC-PRUNE-{$suffix}"]),
            'dental_chair_id' => DentalChair::factory()->create(['code' => "CHAIR-PRUNE-{$suffix}"]),
            'appointment_type_id' => AppointmentType::factory()->create(['name' => "Tipo prune {$suffix}"]),
            'scheduled_at' => Carbon::now()->addDays(2)->startOfHour(),
            'duration_minutes' => 30,
        ]);
    }

    public function test_dry_run_reports_the_plan_without_writing(): void
    {
        $dentist = $this->userByEmail('odontologo+odontologo@test.com');
        $admin = $this->userByEmail('ever+administrador@test.com');
        $appointment = $this->appointmentFor($dentist, $admin);

        // Simulate the polluted base the cleanup is meant to repair.
        DB::table('users')->where('email', self::GROUP_EMAIL)->update(['name' => 'Admin Test']);
        DB::table('users')->where('email', self::RECEPTION_EMAIL)->update(['name' => 'Recepcionista Test']);

        $this->artisan('demo:prune-test-users', ['--dry-run' => true])
            ->expectsOutputToContain('dry-run')
            ->expectsOutputToContain('Cuentas a depurar: 6')
            ->assertSuccessful();

        $this->assertSame(15, User::query()->count());
        $this->assertSame($dentist->id, $appointment->fresh()->user_id);
        $this->assertSame('Admin Test', $this->userByEmail(self::GROUP_EMAIL)->name);
        $this->assertSame('Recepcionista Test', $this->userByEmail(self::RECEPTION_EMAIL)->name);
    }

    public function test_it_reassigns_dependencies_renames_the_retained_accounts_and_deletes_six(): void
    {
        $admin = $this->userByEmail('ever+administrador@test.com');
        $dentist = $this->userByEmail('odontologo+odontologo@test.com');
        $implantologist = $this->userByEmail('implantologo+implantologo@test.com');
        $finance = $this->userByEmail('finanzas+finanzas@test.com');

        $appointment = $this->appointmentFor($dentist, $admin);
        $implantAppointment = $this->appointmentFor($implantologist, $admin, '2');

        $session = CashRegisterSession::factory()->create([
            'user_id' => $finance->id,
            'branch_id' => $this->branch()->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $auditId = DB::table('audit_logs')->insertGetId([
            'user_id' => $admin->id,
            'auditable_type' => Appointment::class,
            'auditable_id' => $appointment->id,
            'action' => 'appointment_created',
            'old_values' => '[]',
            'new_values' => '[]',
            'metadata' => '[]',
            'is_immutable' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin->createToken('demo-prune-test');

        // Simulate the polluted base the cleanup is meant to repair.
        DB::table('users')->where('email', self::GROUP_EMAIL)->update(['name' => 'Admin Test']);
        DB::table('users')->where('email', self::RECEPTION_EMAIL)->update(['name' => 'Recepcionista Test']);

        $this->artisan('demo:prune-test-users')
            ->expectsOutputToContain('Cuentas eliminadas: 6')
            ->expectsOutputToContain('Usuarios restantes: 9')
            ->assertSuccessful();

        $this->assertSame(9, User::query()->count());
        $this->assertSame([], User::query()->whereIn('email', self::PRUNED_EMAILS)->pluck('email')->all());

        $ever = $this->userByEmail('ever+odontologo@test.com');
        $wilmer = $this->userByEmail('wilmer+implantologo@test.com');
        $elizabet = $this->userByEmail('elizabet+administrador@test.com');
        $milagros = $this->userByEmail('milagros+finanzas@test.com');

        $this->assertSame($ever->id, $appointment->fresh()->user_id);
        $this->assertSame($elizabet->id, $appointment->fresh()->created_by);
        $this->assertSame($wilmer->id, $implantAppointment->fresh()->user_id);
        $this->assertSame($milagros->id, $session->fresh()->user_id);
        $this->assertSame($elizabet->id, (int) DB::table('audit_logs')->where('id', $auditId)->value('user_id'));

        $this->assertSame(0, DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $admin->id)
            ->count());

        $group = $this->userByEmail(self::GROUP_EMAIL);
        $this->assertSame('Gerencia OdontoSuite', $group->name);
        $this->assertNull($group->branch_id);
        $this->assertSame('Rosa Quispe Flores', $this->userByEmail(self::RECEPTION_EMAIL)->name);
        $this->assertSame(1, User::query()->whereNull('branch_id')->count());
    }

    public function test_a_second_run_is_a_no_op(): void
    {
        $this->artisan('demo:prune-test-users')->assertSuccessful();

        $this->artisan('demo:prune-test-users')
            ->expectsOutputToContain('Cuentas a depurar: 0')
            ->assertSuccessful();

        $this->assertSame(9, User::query()->count());
        $this->assertSame('Gerencia OdontoSuite', $this->userByEmail(self::GROUP_EMAIL)->name);
        $this->assertSame('Rosa Quispe Flores', $this->userByEmail(self::RECEPTION_EMAIL)->name);
    }

    public function test_a_slot_collision_aborts_without_changing_anything(): void
    {
        $admin = $this->userByEmail('ever+administrador@test.com');
        $dentist = $this->userByEmail('odontologo+odontologo@test.com');
        $ever = $this->userByEmail('ever+odontologo@test.com');

        $slot = Carbon::now()->addDays(3)->startOfHour();

        $source = $this->appointmentFor($dentist, $admin);
        $source->forceFill(['scheduled_at' => $slot, 'ends_at' => $slot->copy()->addMinutes(30)])->save();

        $target = $this->appointmentFor($ever, $ever, '2');
        $target->forceFill(['scheduled_at' => $slot, 'ends_at' => $slot->copy()->addMinutes(30)])->save();

        // Simulate the polluted base the cleanup is meant to repair.
        DB::table('users')->where('email', self::GROUP_EMAIL)->update(['name' => 'Admin Test']);
        DB::table('users')->where('email', self::RECEPTION_EMAIL)->update(['name' => 'Recepcionista Test']);

        $this->artisan('demo:prune-test-users')
            ->expectsOutputToContain('choca')
            ->assertFailed();

        $this->assertSame(15, User::query()->count());
        $this->assertSame($dentist->id, $source->fresh()->user_id);
        $this->assertSame('Admin Test', $this->userByEmail(self::GROUP_EMAIL)->name);
        $this->assertSame('Recepcionista Test', $this->userByEmail(self::RECEPTION_EMAIL)->name);
    }
}
