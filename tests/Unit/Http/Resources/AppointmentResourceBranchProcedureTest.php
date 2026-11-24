<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ProcedureCatalog;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * T5 / S6 — the appointment payload publishes its branch (sede) and its
 * procedure only when both relations arrive eager-loaded, so the detail can
 * show both rows without a lazy query per row. The keys are absent when the
 * relation is not loaded, and a null FK (loaded relation, no row) publishes an
 * explicit null instead of inventing data.
 *
 * Deliberately DB-free, mirroring the recipe of
 * `tests/Unit/Resources/PatientResourceAgeTest`: a throwaway in-memory SQLite
 * Capsule lets the model's date attributes resolve, no migration runs and no
 * row is persisted. `setRelation()` is what `whenLoaded()` reads.
 */
class AppointmentResourceBranchProcedureTest extends TestCase
{
    private const CONTROLLER_PATH = '/app/Http/Controllers/Api/AppointmentController.php';

    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setEventDispatcher(new Dispatcher);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        Appointment::clearBootedModels();
        Branch::clearBootedModels();
        ProcedureCatalog::clearBootedModels();

        parent::tearDown();
    }

    private function request(): Request
    {
        $request = Request::create('/api/appointments');
        // The resource reads `$request->user()?->role` to decide whether the
        // clinical `treatment_notes` key travels. A DB-free test has no
        // session, so the guest is resolved explicitly.
        $request->setUserResolver(fn () => null);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Appointment $appointment): array
    {
        // `resolve()` is what filters the `MissingValue` markers that
        // `whenLoaded()` emits; a raw `toArray()` would keep the keys.
        return (new AppointmentResource($appointment))->resolve($this->request());
    }

    private function appointment(): Appointment
    {
        $appointment = new Appointment;
        $appointment->setAttribute('id', 11);
        $appointment->setAttribute('patient_id', 7);
        $appointment->setAttribute('user_id', 2);
        $appointment->setAttribute('status', 'confirmed');
        $appointment->setAttribute('duration_minutes', 30);
        $appointment->exists = false;

        return $appointment;
    }

    /**
     * Extract one controller method body so the select/with/load contract is
     * asserted on the method that owns it, never on the whole file.
     */
    private function methodSource(string $source, string $method): string
    {
        $start = strpos($source, 'public function '.$method.'(');
        $this->assertNotFalse($start, sprintf('AppointmentController MUST declare `%s()`.', $method));

        $next = strpos($source, 'public function ', (int) $start + 1);
        $length = $next === false ? null : $next - (int) $start;

        return substr($source, (int) $start, $length);
    }

    #[Test]
    public function appointment_declares_the_branch_and_procedure_relations(): void
    {
        $appointment = new Appointment;
        $appointment->exists = false;

        $branch = $appointment->branch();
        $this->assertInstanceOf(BelongsTo::class, $branch);
        $this->assertInstanceOf(Branch::class, $branch->getRelated());
        $this->assertSame(
            'branch_id',
            $branch->getForeignKeyName(),
            'Appointment::branch() MUST resolve through the existing `branch_id` column (no migration).'
        );

        $procedure = $appointment->procedure();
        $this->assertInstanceOf(BelongsTo::class, $procedure);
        $this->assertInstanceOf(ProcedureCatalog::class, $procedure->getRelated());
        $this->assertSame('procedure_id', $procedure->getForeignKeyName());
    }

    #[Test]
    public function payload_carries_branch_and_procedure_when_loaded(): void
    {
        $branch = new Branch(['name' => 'San Isidro']);
        $branch->setAttribute('id', 3);

        $procedure = new ProcedureCatalog(['name' => 'Resina compuesta', 'code' => 'PROC-001']);
        $procedure->setAttribute('id', 5);

        $appointment = $this->appointment();
        $appointment->setRelation('branch', $branch);
        $appointment->setRelation('procedure', $procedure);

        $array = $this->payload($appointment);

        $this->assertArrayHasKey(
            'branch',
            $array,
            'AppointmentResource MUST publish `branch` when the relation is loaded (S6).'
        );
        $this->assertSame(
            ['id' => 3, 'name' => 'San Isidro'],
            $array['branch'],
            'The `branch` payload MUST carry exactly id and name.'
        );

        $this->assertArrayHasKey(
            'procedure',
            $array,
            'AppointmentResource MUST publish `procedure` when the relation is loaded (S6).'
        );
        $this->assertSame(
            ['id' => 5, 'name' => 'Resina compuesta', 'code' => 'PROC-001'],
            $array['procedure'],
            'The `procedure` payload MUST carry id, name and the catalog code.'
        );
    }

    #[Test]
    public function payload_omits_both_keys_when_the_relations_are_not_loaded(): void
    {
        $array = $this->payload($this->appointment());

        $this->assertArrayNotHasKey(
            'branch',
            $array,
            'A surface that does not eager-load `branch` MUST NOT receive the key (S6: no lazy queries).'
        );
        $this->assertArrayNotHasKey(
            'procedure',
            $array,
            'A surface that does not eager-load `procedure` MUST NOT receive the key (S6: no lazy queries).'
        );

        // Additive-only: the keys the detail already read keep travelling.
        foreach (['id', 'patient_id', 'user_id', 'status', 'duration_minutes'] as $key) {
            $this->assertArrayHasKey($key, $array, sprintf('The existing `%s` key MUST remain in the payload.', $key));
        }
    }

    #[Test]
    public function loaded_relations_without_a_row_publish_null(): void
    {
        $appointment = $this->appointment();
        $appointment->setRelation('branch', null);
        $appointment->setRelation('procedure', null);

        $array = $this->payload($appointment);

        $this->assertArrayHasKey('branch', $array);
        $this->assertNull($array['branch'], 'A loaded but absent branch MUST publish null, never invented data.');
        $this->assertArrayHasKey('procedure', $array);
        $this->assertNull($array['procedure'], 'A loaded but absent procedure MUST publish null, never invented data.');
    }

    #[Test]
    public function index_selects_the_foreign_keys_and_eager_loads_both_relations(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4).self::CONTROLLER_PATH);
        $index = $this->methodSource($source, 'index');

        $this->assertSame(
            1,
            preg_match('/Appointment::select\(\[(.*?)\]\)/s', $index, $matches),
            'AppointmentController@index MUST declare the appointment select list.'
        );
        $select = $matches[1];

        foreach (['branch_id', 'procedure_id'] as $foreignKey) {
            $this->assertStringContainsString(
                "'".$foreignKey."'",
                $select,
                sprintf('The index select MUST include `%s`, otherwise the relation cannot resolve.', $foreignKey)
            );
        }

        foreach (['branch:id,name', 'procedure:id,name,code'] as $relation) {
            $this->assertStringContainsString(
                "'".$relation."'",
                $index,
                sprintf('AppointmentController@index MUST eager-load `%s` (S6: no lazy query per row).', $relation)
            );
        }
    }

    #[Test]
    public function show_eager_loads_both_relations(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4).self::CONTROLLER_PATH);
        $show = $this->methodSource($source, 'show');

        foreach (['branch:id,name', 'procedure:id,name,code'] as $relation) {
            $this->assertStringContainsString(
                "'".$relation."'",
                $show,
                sprintf('AppointmentController@show MUST eager-load `%s` (S6).', $relation)
            );
        }
    }
}
