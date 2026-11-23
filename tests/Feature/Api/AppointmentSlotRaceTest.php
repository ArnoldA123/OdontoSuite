<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The window between two desks booking the same hole.
 *
 * The pre-check reads; the unique index keys the exact interval, so it answers
 * only for an identical `(professional|chair, start, end)` and is blind to
 * every partial overlap: 09:00-09:30 and 09:15-09:45 are different keys and
 * both inserts succeed. The write doors now take a pessimistic lock on the
 * professional and on the chair before they read, inside the transaction that
 * writes, so the second desk waits for the first commit and then reads the row
 * that commit published.
 *
 * These tests use a real second MySQL connection in a child process — not a
 * second Laravel connection in the same process, which could not block while
 * the test is blocked in the same thread. The child takes the row lock the door
 * is about to take, books an overlapping appointment, and commits after a
 * measurable hold; the request must be blocked for that hold and then answer
 * the unchanged 422. `DatabaseTruncation` instead of `RefreshDatabase` because
 * the child writes committed rows and a wrapping test transaction would hide
 * them from it.
 *
 * Needs a real schema, so Tests\TestCase skips it on any non-MySQL runner
 * (issue #170); run it with phpunit.mysql.xml — see AGENTS.md §6 (decision #79).
 */
class AppointmentSlotRaceTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * `DatabaseTruncation` limpia al entrar, no al salir: sin este barrido la
     * ultima prueba de la clase dejaria filas para el resto de la suite, que
     * corre con `RefreshDatabase` y espera una base vacia. La carrera exige dos
     * conexiones reales, asi que la transaccion no es una opcion.
     */
    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();
        parent::tearDown();
    }

    /**
     * How long the second connection keeps the lock before it commits. The
     * request is expected to wait at least most of it.
     */
    private const HOLD_MICROSECONDS = 700_000;

    private function actor(): User
    {
        return User::factory()->create([
            'role' => 'recepcionista',
            'is_active' => true,
        ]);
    }

    private function dentist(): User
    {
        return User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    /** A working day far enough ahead for the future rule of the doors. */
    private function day(): Carbon
    {
        return now()->addDays(5)->startOfDay();
    }

    private function at(string $clock): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $clock));

        return $this->day()->copy()->setTime($hour, $minute);
    }

    private function book(
        User $actor,
        User $dentist,
        DentalChair $chair,
        Carbon $startsAt,
        int $duration = 30
    ): TestResponse {
        return $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', [
                'patient_id' => Patient::factory()->create()->id,
                'user_id' => $dentist->id,
                'dental_chair_id' => $chair->id,
                'appointment_type_id' => AppointmentType::factory()->create()->id,
                'scheduled_at' => $startsAt->toDateTimeString(),
                'duration_minutes' => $duration,
            ]);
    }

    public function test_the_second_desk_waits_on_the_professional_lock_and_then_answers_the_same_422(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        // The rival books 09:00-09:30 while holding the professional's row.
        $contender = $this->startContender('users', $dentist->id, [
            $this->patientId(),
            $dentist->id,
            $chair->id,
            $this->appointmentTypeId(),
            $this->at('09:00')->toDateTimeString(),
            $this->at('09:30')->toDateTimeString(),
            30,
            $actor->id,
        ]);

        try {
            $this->awaitLine($contender, 'locked');

            $startedAt = microtime(true);
            $response = $this->book($actor, $dentist, $chair, $this->at('09:15'));
            $waitedMs = (microtime(true) - $startedAt) * 1000;

            $response->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
            $this->assertStringContainsString(
                'Conflicto de horario',
                (string) $response->json('errors.scheduled_at.0')
            );

            // It did not read before the lock: it waited for the commit that
            // published the rival's row.
            $this->assertGreaterThan(
                self::HOLD_MICROSECONDS / 1000 * 0.6,
                $waitedMs,
                sprintf('The door answered in %.0f ms instead of waiting for the lock.', $waitedMs)
            );

            $this->awaitLine($contender, 'committed');
        } finally {
            $this->stopContender($contender);
        }

        // Only the rival's booking exists: the door answered instead of
        // inserting a second, partially overlapping appointment.
        $this->assertSame(1, Appointment::count());
        $this->assertSame(
            $this->at('09:00')->format('Y-m-d H:i:s'),
            Appointment::firstOrFail()->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    public function test_the_second_desk_waits_on_the_chair_lock_when_the_professional_differs(): void
    {
        $actor = $this->actor();
        $requestingDentist = $this->dentist();
        $otherDentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        // Different professionals, same chair: only the chair row can
        // serialize the two writes.
        $contender = $this->startContender('dental_chairs', $chair->id, [
            $this->patientId(),
            $otherDentist->id,
            $chair->id,
            $this->appointmentTypeId(),
            $this->at('09:00')->toDateTimeString(),
            $this->at('09:30')->toDateTimeString(),
            30,
            $actor->id,
        ]);

        try {
            $this->awaitLine($contender, 'locked');

            $startedAt = microtime(true);
            $response = $this->book($actor, $requestingDentist, $chair, $this->at('09:15'));
            $waitedMs = (microtime(true) - $startedAt) * 1000;

            $response->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
            $this->assertGreaterThan(
                self::HOLD_MICROSECONDS / 1000 * 0.6,
                $waitedMs,
                sprintf('The door answered in %.0f ms instead of waiting for the chair lock.', $waitedMs)
            );

            $this->awaitLine($contender, 'committed');
        } finally {
            $this->stopContender($contender);
        }

        $this->assertSame(1, Appointment::count());
        $this->assertSame($otherDentist->id, (int) Appointment::firstOrFail()->user_id);
    }

    public function test_reschedule_that_loses_the_race_answers_the_conflict_envelope(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $moving = Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => $this->at('11:00'),
            'ends_at' => $this->at('11:30'),
            'duration_minutes' => 30,
        ]);

        $contender = $this->startContender('users', $dentist->id, [
            $this->patientId(),
            $dentist->id,
            $chair->id,
            $this->appointmentTypeId(),
            $this->at('09:00')->toDateTimeString(),
            $this->at('09:30')->toDateTimeString(),
            30,
            $actor->id,
        ]);

        try {
            $this->awaitLine($contender, 'locked');

            $startedAt = microtime(true);
            $response = $this->actingAs($actor, 'sanctum')
                ->patchJson("/api/appointments/{$moving->id}/reschedule", [
                    'scheduled_at' => $this->at('09:15')->toDateTimeString(),
                    'duration_minutes' => 30,
                ]);
            $waitedMs = (microtime(true) - $startedAt) * 1000;

            $response->assertStatus(422)
                ->assertJsonValidationErrors('scheduled_at')
                ->assertJsonPath('conflict.type', 'user_conflict');
            $this->assertNotNull($response->json('conflict.conflicting_appointment'));
            $this->assertGreaterThan(
                self::HOLD_MICROSECONDS / 1000 * 0.6,
                $waitedMs,
                sprintf('The reschedule answered in %.0f ms instead of waiting for the lock.', $waitedMs)
            );

            $this->awaitLine($contender, 'committed');
        } finally {
            $this->stopContender($contender);
        }

        $this->assertSame(
            $this->at('11:00')->format('Y-m-d H:i:s'),
            $moving->fresh()->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    private function patientId(): int
    {
        return Patient::factory()->create()->id;
    }

    private function appointmentTypeId(): int
    {
        return AppointmentType::factory()->create()->id;
    }

    /**
     * Start the rival writer: a separate PHP process with its own MySQL
     * connection, because a second connection inside this process could not
     * hold the lock while the test blocks on the same thread.
     *
     * @param  array<int, mixed>                                            $booking bindings of the insert below
     * @return array{process: resource, stdout: resource, stderr: resource}
     */
    private function startContender(string $lockTable, int $lockId, array $booking): array
    {
        $this->assertContains($lockTable, ['users', 'dental_chairs']);

        $connection = config('database.connections.mysql');

        $dsn = $connection['unix_socket']
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $connection['unix_socket'], $connection['database'])
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $connection['host'], $connection['port'], $connection['database']);

        $script = <<<'PHP'
            $payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);

            $pdo = new PDO($payload['dsn'], $payload['username'], $payload['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->beginTransaction();
            $pdo->prepare(sprintf('SELECT `id` FROM `%s` WHERE `id` = ? FOR UPDATE', $payload['lock_table']))
                ->execute([$payload['lock_id']]);

            fwrite(STDOUT, "locked\n");
            fflush(STDOUT);

            usleep($payload['hold_microseconds']);

            $pdo->prepare(
                'INSERT INTO `appointments` (`patient_id`, `user_id`, `dental_chair_id`, `appointment_type_id`, '
                .'`scheduled_at`, `ends_at`, `duration_minutes`, `status`, `created_by`, `created_at`, `updated_at`) '
                .'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            )->execute($payload['booking']);

            $pdo->commit();

            fwrite(STDOUT, "committed\n");
            fflush(STDOUT);
            PHP;

        $process = proc_open(
            [PHP_BINARY, '-r', $script],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        $this->assertIsResource($process, 'Could not start the second connection.');

        // The status is inserted between the duration and the author, in the
        // same order as the insert above.
        $bindings = $booking;
        array_splice($bindings, 7, 0, ['scheduled']);

        fwrite($pipes[0], json_encode([
            'dsn' => $dsn,
            'username' => $connection['username'],
            'password' => $connection['password'],
            'lock_table' => $lockTable,
            'lock_id' => $lockId,
            'hold_microseconds' => self::HOLD_MICROSECONDS,
            'booking' => $bindings,
        ], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);

        return ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }

    /**
     * Wait for an exact line of the rival, failing with its stderr when it dies
     * instead of blocking the suite.
     *
     * @param array{process: resource, stdout: resource, stderr: resource} $contender
     */
    private function awaitLine(array $contender, string $line): void
    {
        $output = $this->readUntil($contender['stdout'], $line, 20.0);

        if (! str_contains($output, $line)) {
            $this->fail(sprintf(
                'The rival never reported "%s". stdout: %s stderr: %s',
                $line,
                trim($output),
                trim((string) stream_get_contents($contender['stderr']))
            ));
        }
    }

    /**
     * Non-blocking read with a deadline: pipes do not honour
     * `stream_set_timeout` on every platform.
     */
    private function readUntil($pipe, string $needle, float $timeoutSeconds): string
    {
        stream_set_blocking($pipe, false);

        $buffer = '';
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $chunk = fread($pipe, 8192);

            if (is_string($chunk) && $chunk !== '') {
                $buffer .= $chunk;

                if (str_contains($buffer, $needle)) {
                    return $buffer;
                }

                continue;
            }

            if (feof($pipe)) {
                break;
            }

            usleep(2000);
        }

        return $buffer;
    }

    /**
     * @param array{process: resource, stdout: resource, stderr: resource} $contender
     */
    private function stopContender(array $contender): void
    {
        // The rival exits on its own after the commit; terminate it only if it
        // is still alive, so a failed assertion cannot hang the suite.
        $deadline = microtime(true) + 10.0;

        while (microtime(true) < $deadline) {
            $status = proc_get_status($contender['process']);

            if (! $status['running']) {
                break;
            }

            usleep(10000);
        }

        if (proc_get_status($contender['process'])['running']) {
            proc_terminate($contender['process']);
        }

        foreach (['stdout', 'stderr'] as $pipe) {
            if (is_resource($contender[$pipe])) {
                fclose($contender[$pipe]);
            }
        }

        proc_close($contender['process']);
    }
}
