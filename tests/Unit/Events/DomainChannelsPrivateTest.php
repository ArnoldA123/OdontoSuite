<?php

namespace Tests\Unit\Events;

use App\Events\AppointmentCompleted;
use App\Events\AppointmentCreated;
use App\Events\AppointmentDeleted;
use App\Events\AppointmentUpdated;
use App\Events\CashMovementCreated;
use App\Events\CashSessionClosed;
use App\Events\CashSessionOpened;
use App\Events\ClinicalAttachmentCreated;
use App\Events\ClinicalEvolutionCreated;
use App\Events\ClinicalEvolutionDeleted;
use App\Events\ClinicalEvolutionUpdated;
use App\Events\MedicalRecordCreated;
use App\Events\MedicalRecordUpdated;
use App\Events\PatientFileExported;
use App\Events\PaymentRegistered;
use App\Events\ProcedureCatalogDeactivated;
use App\Events\ProcedureCatalogUpdated;
use App\Events\ReminderSent;
use App\Events\SpecialtyRecordCreated;
use App\Events\SpecialtyRecordUpdated;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Events\TreatmentPlanCreated;
use App\Events\TreatmentPlanDeleted;
use App\Events\TreatmentPlanUpdated;
use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\OrthodonticsRecord;
use App\Models\Patient;
use App\Models\ProcedureCatalog;
use App\Models\ReminderSchedule;
use App\Models\Transaction;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;

/**
 * Issue #249 — no domain event may publish on a public Reverb channel.
 *
 * A public channel verifies nothing: anyone holding the app key subscribes and
 * receives the payload without authenticating. #183 closed `dashboard-updates`;
 * the same events kept publishing on public domain channels, so the leak was
 * still open. This guard pins the two properties that make it impossible for
 * the 27 publishers measured at `main@a2c7101`: every publisher names a
 * PrivateChannel, and no file under `app/Events` imports or instantiates the
 * public Channel class.
 */
class DomainChannelsPrivateTest extends TestCase
{
    /**
     * Every event that still published on a public domain channel, with the
     * channel names it targets. A new publisher must be added here so it
     * inherits the guard.
     *
     * @return array<string, array{string, string[]}>
     */
    public static function domainChannelEvents(): array
    {
        $map = [
            AppointmentCreated::class => ['appointments'],
            AppointmentUpdated::class => ['appointments'],
            AppointmentDeleted::class => ['appointments'],
            AppointmentCompleted::class => ['appointments'],
            ReminderSent::class => ['appointments', 'reminders'],
            MedicalRecordCreated::class => ['medical-records'],
            MedicalRecordUpdated::class => ['medical-records'],
            ClinicalEvolutionCreated::class => ['medical-records'],
            ClinicalEvolutionUpdated::class => ['medical-records'],
            ClinicalEvolutionDeleted::class => ['medical-records'],
            ClinicalAttachmentCreated::class => ['medical-records'],
            PaymentRegistered::class => ['cash-register'],
            CashSessionOpened::class => ['cash-register'],
            CashSessionClosed::class => ['cash-register'],
            CashMovementCreated::class => ['cash-register'],
            TreatmentPlanCreated::class => ['treatment-plans'],
            TreatmentPlanUpdated::class => ['treatment-plans'],
            TreatmentPlanDeleted::class => ['treatment-plans'],
            TransactionCreated::class => ['transactions'],
            TransactionUpdated::class => ['transactions'],
            SpecialtyRecordCreated::class => ['specialty-records'],
            SpecialtyRecordUpdated::class => ['specialty-records'],
            ProcedureCatalogUpdated::class => ['procedure-catalog'],
            ProcedureCatalogDeactivated::class => ['procedure-catalog'],
            UserCreated::class => ['users'],
            UserUpdated::class => ['users'],
        ];

        $data = [];

        foreach ($map as $eventClass => $channels) {
            foreach ($channels as $channel) {
                $shortName = substr($eventClass, strrpos($eventClass, '\\') + 1);
                $data["{$shortName} on {$channel}"] = [$eventClass, $channel];
            }
        }

        return $data;
    }

    /**
     * @test
     *
     * @dataProvider domainChannelEvents
     */
    public function domain_event_publishes_on_the_private_variant_of_its_channel(
        string $eventClass,
        string $channel
    ): void {
        $source = $this->source($eventClass);
        $shortName = substr($eventClass, strrpos($eventClass, '\\') + 1);

        $this->assertStringContainsString(
            "new PrivateChannel('{$channel}')",
            $source,
            "{$shortName} MUST publish `{$channel}` as a PrivateChannel (issue #249)."
        );
        $this->assertStringNotContainsString(
            "new Channel('{$channel}')",
            $source,
            "{$shortName} MUST NOT keep the public Channel for `{$channel}` (issue #249)."
        );
    }

    /**
     * @test
     */
    public function no_event_under_app_events_instantiates_the_public_channel_class(): void
    {
        $offenders = [];

        foreach (File::files(app_path('Events')) as $file) {
            $source = $file->getContents();

            if (str_contains($source, 'new Channel(')) {
                $offenders[] = $file->getFilename().' instantiates new Channel()';
            }

            if (str_contains($source, 'use Illuminate\Broadcasting\Channel;')) {
                $offenders[] = $file->getFilename().' imports the public Channel class';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Every domain event MUST publish on a PrivateChannel (issue #249): '.implode('; ', $offenders)
        );
    }

    /**
     * @test
     */
    public function patient_file_exported_targets_the_exporting_user_privately(): void
    {
        $patient = new Patient;
        $patient->id = 1;

        $user = new User;
        $user->id = 456;

        $channels = (new PatientFileExported($patient, $user, 'pdf', '/tmp/export.pdf'))->broadcastOn();

        $userChannel = collect($channels)->first(
            fn ($channel) => str_ends_with($channel->name, 'user.456')
        );

        $this->assertNotNull($userChannel, 'PatientFileExported MUST notify the exporting user.');
        $this->assertInstanceOf(PrivateChannel::class, $userChannel);
        $this->assertSame('private-user.456', $userChannel->name);
    }

    /**
     * @test
     */
    public function every_closed_channel_carries_the_private_prefix_on_the_wire(): void
    {
        $channels = [
            'appointments' => (new AppointmentCreated($this->model(Appointment::class, 1)))->broadcastOn(),
            'reminders' => (new ReminderSent($this->model(ReminderSchedule::class, 1)))->broadcastOn(),
            'medical-records' => (new MedicalRecordCreated($this->model(MedicalRecord::class, 1)))->broadcastOn(),
            'cash-register' => (new PaymentRegistered($this->model(Transaction::class, 1)))->broadcastOn(),
            'treatment-plans' => (new TreatmentPlanCreated($this->model(TreatmentPlan::class, 1)))->broadcastOn(),
            'transactions' => (new TransactionCreated($this->model(Transaction::class, 1)))->broadcastOn(),
            'specialty-records' => (new SpecialtyRecordCreated(
                $this->model(OrthodonticsRecord::class, 1),
                'general'
            ))->broadcastOn(),
            'procedure-catalog' => (new ProcedureCatalogUpdated(
                $this->model(ProcedureCatalog::class, 1),
                'updated',
                [],
                null
            ))->broadcastOn(),
            'users' => (new UserCreated($this->model(User::class, 1)))->broadcastOn(),
            'cash-session.7' => (new PaymentRegistered($this->model(Transaction::class, 1), 7))->broadcastOn(),
        ];

        foreach ($channels as $base => $emitted) {
            $match = collect($emitted)->first(
                fn ($channel) => str_ends_with($channel->name, $base)
            );

            $this->assertNotNull($match, "The publisher for `{$base}` MUST broadcast on it.");
            $this->assertInstanceOf(PrivateChannel::class, $match, "`{$base}` MUST be a PrivateChannel.");
            $this->assertSame("private-{$base}", $match->name, "`{$base}` MUST reach the wire as `private-{$base}`.");
        }
    }

    /**
     * @test
     */
    public function the_guard_still_covers_every_measured_publisher(): void
    {
        $this->assertCount(
            26,
            array_unique(array_map(
                fn (array $row) => $row[0],
                self::domainChannelEvents()
            )),
            'The guard MUST keep the 26 measured publishers; add new ones to this provider.'
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

    private function source(string $eventClass): string
    {
        return file_get_contents((new ReflectionClass($eventClass))->getFileName());
    }
}
