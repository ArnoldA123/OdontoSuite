<?php

namespace Tests\Unit\Events;

use App\Events\MedicalRecordCreated;
use App\Events\SpecialtyRecordCreated;
use App\Events\SpecialtyRecordUpdated;
use App\Http\Controllers\Api\BroadcastingAuthController;
use App\Models\MedicalRecord;
use App\Models\OrthodonticsRecord;
use Illuminate\Broadcasting\PrivateChannel;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * F5 (#315, D3) — a channel cannot vary its payload per role.
 *
 * `dashboard-updates` authorizes the seven roles, so every subscriber receives
 * the same payload; there is no per-desk variant. An event whose payload is a
 * clinical document therefore travels only on the channel of the desks that
 * read it: `MedicalRecordCreated` on `medical-records` and the specialty record
 * events on `specialty-records`. The dashboard refreshes its counters with the
 * agenda, patient and money events, whose payload carries no clinical
 * narrative.
 *
 * The guard is source-level and runtime-level on the emitted channel list, so
 * it runs without a database and without a broadcaster.
 */
class ClinicalEventsChannelBoundaryTest extends TestCase
{
    /**
     * Every event that carries a clinical document, with the only channel it
     * may publish on. A new clinical publisher must be added here so it
     * inherits the guard.
     *
     * @return array<string, array{class-string, string}>
     */
    public static function clinicalEvents(): array
    {
        return [
            'MedicalRecordCreated' => [MedicalRecordCreated::class, 'medical-records'],
            'SpecialtyRecordCreated' => [SpecialtyRecordCreated::class, 'specialty-records'],
            'SpecialtyRecordUpdated' => [SpecialtyRecordUpdated::class, 'specialty-records'],
        ];
    }

    #[Test]
    #[DataProvider('clinicalEvents')]
    public function clinical_document_publishes_only_on_its_clinical_channel(string $eventClass, string $channel): void
    {
        $shortName = substr($eventClass, strrpos($eventClass, '\\') + 1);
        $emitted = collect($this->event($eventClass)->broadcastOn())->values();

        $dashboard = $emitted->first(
            fn ($emittedChannel) => str_ends_with($emittedChannel->name, 'dashboard-updates')
        );

        $this->assertNull(
            $dashboard,
            "{$shortName} MUST NOT broadcast on `dashboard-updates`: that channel authorizes the seven roles and a channel cannot vary its payload per role (F5 #315, D3)."
        );

        $this->assertCount(
            1,
            $emitted,
            "{$shortName} MUST broadcast on exactly one channel, `{$channel}` (F5 #315, D3)."
        );

        $clinical = $emitted->first();

        $this->assertInstanceOf(
            PrivateChannel::class,
            $clinical,
            "{$shortName} MUST publish `{$channel}` as a PrivateChannel (F5 #315, D3)."
        );
        $this->assertSame(
            "private-{$channel}",
            $clinical->name,
            "{$shortName} MUST reach the wire as `private-{$channel}` (F5 #315, D3)."
        );
    }

    #[Test]
    #[DataProvider('clinicalEvents')]
    public function broadcast_on_never_names_the_channel_shared_by_every_role(string $eventClass, string $channel): void
    {
        $shortName = substr($eventClass, strrpos($eventClass, '\\') + 1);

        $this->assertStringNotContainsString(
            'dashboard-updates',
            $this->methodSource($eventClass, 'broadcastOn'),
            "{$shortName}::broadcastOn() MUST NOT name `dashboard-updates`: the clinical document travels only on its own channel (F5 #315, D3)."
        );
        $this->assertStringNotContainsString(
            "new Channel('".$channel."')",
            $this->methodSource($eventClass, 'broadcastOn'),
            "{$shortName} MUST NOT keep the public Channel for `{$channel}` (issue #249)."
        );
    }

    #[Test]
    public function medical_record_created_still_carries_the_clinical_document(): void
    {
        $payload = $this->methodSource(MedicalRecordCreated::class, 'broadcastWith');

        $this->assertStringContainsString(
            "'medical_record'",
            $payload,
            'MedicalRecordCreated::broadcastWith() MUST keep the clinical document: it no longer travels on a channel open to every role, so the payload is not what protects the boundary (F5 #315, D3).'
        );
        $this->assertStringContainsString(
            '$this->medicalRecord',
            $payload,
            'MedicalRecordCreated::broadcastWith() MUST serialize the record itself, not a reduced counter payload (F5 #315, D3).'
        );
    }

    #[Test]
    public function dashboard_updates_still_authorizes_the_seven_roles(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(BroadcastingAuthController::class))->getFileName()
        );

        $this->assertMatchesRegularExpression(
            "/'dashboard-updates'\\s*=>\\s*self::ALL_ROLES/",
            $source,
            '`dashboard-updates` MUST keep ALL_ROLES: it is the widest channel, and that is exactly why a clinical document does not travel on it (F5 #315, D3).'
        );

        $this->assertSame(
            [
                'administrador', 'recepcionista', 'odontologo', 'implantologo',
                'tecnico_dental', 'asistente', 'finanzas',
            ],
            $this->constantList($source, 'ALL_ROLES'),
            '`dashboard-updates` MUST authorize exactly the seven roles; a role joining ALL_ROLES joins the widest channel, so add it here deliberately (F5 #315, S4).'
        );
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T> $class
     * @return T
     */
    private function model(string $class, int $id)
    {
        $model = new $class;
        $model->id = $id;

        return $model;
    }

    private function event(string $eventClass): object
    {
        return match ($eventClass) {
            MedicalRecordCreated::class => new MedicalRecordCreated($this->model(MedicalRecord::class, 1)),
            SpecialtyRecordCreated::class, SpecialtyRecordUpdated::class => new $eventClass(
                $this->model(OrthodonticsRecord::class, 1),
                'general'
            ),
            default => throw new InvalidArgumentException("No fixture for {$eventClass}"),
        };
    }

    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $lines = (array) file((string) $reflection->getFileName());

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

    /**
     * String literals of a `private const NAME = [...]` array in the source.
     *
     * @return string[]
     */
    private function constantList(string $source, string $constant): array
    {
        $this->assertSame(
            1,
            preg_match('/private const '.$constant.' = \[(.*?)\];/s', $source, $block),
            "BroadcastingAuthController MUST declare the {$constant} constant."
        );

        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $block[1], $values);

        return $values[1];
    }
}
