<?php

namespace Tests\Unit\Events;

use App\Events\PatientFileExported;
use App\Events\QuotationApproved;
use App\Events\QuotationCreated;
use App\Events\TreatmentPlanCreated;
use App\Providers\AppServiceProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Sprint 0 fix (NF-2): los 26 eventos huérfanos están marcados con
 * @deprecated en su docblock. Verifica que:
 *   1. Los 7 eventos con listener NO están marcados.
 *   2. Los 26 eventos sin listener SÍ están marcados.
 *   3. El nuevo evento PatientFileExported (NF-5) tiene listener cableado.
 */
class OrphanEventsDeprecatedTest extends TestCase
{
    /** @test */
    public function listed_events_should_not_be_marked_deprecated(): void
    {
        $listed = [
            'AppointmentCreated', 'AppointmentUpdated', 'AppointmentDeleted',
            'PatientCreated', 'PatientUpdated', 'PatientDeleted',
            'AppointmentCompleted',
        ];
        foreach ($listed as $eventClass) {
            $file = app_path("Events/{$eventClass}.php");
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringNotContainsString(
                'Sprint 0 fix (NF-2)',
                $content,
                "{$eventClass} should NOT be marked as Sprint 0 fix (NF-2) — it has a listener"
            );
        }
    }

    /** @test */
    public function orphan_events_should_be_marked_deprecated(): void
    {
        $orphans = [
            'AppointmentCheckedIn', 'CashMovementCreated', 'CashSessionClosed',
            'CashSessionOpened', 'ClinicalAttachmentCreated', 'ClinicalEvolutionCreated',
            'InterconsultationCreated', 'InterconsultationResponded',
            'MedicalRecordCreated', 'MedicalRecordUpdated',
            'PaymentRegistered', 'QuotationApproved', 'QuotationCreated',
            'QuotationUpdated', 'ReminderSent', 'SpecialtyRecordCreated',
            'SpecialtyRecordUpdated', 'TransactionCreated', 'TransactionUpdated',
            'TreatmentPlanCreated', 'TreatmentPlanDeleted', 'TreatmentPlanUpdated',
            'UserCreated', 'UserUpdated', 'WaitingListCreated', 'WaitingListFilled',
        ];
        foreach ($orphans as $eventClass) {
            $file = app_path("Events/{$eventClass}.php");
            $this->assertFileExists($file, "Event class {$eventClass} should exist");
            $content = file_get_contents($file);
            $this->assertStringContainsString(
                'Sprint 0 fix (NF-2)',
                $content,
                "{$eventClass} should be marked as Sprint 0 fix (NF-2) orphan event"
            );
        }
    }

    /** @test */
    public function patient_file_exported_is_cabled_in_service_provider(): void
    {
        $provider = new AppServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $boot = $reflection->getMethod('boot');
        $code = file($reflection->getFileName());
        $bootSource = implode('', array_slice($code, $boot->getStartLine() - 1, $boot->getEndLine() - $boot->getStartLine() + 1));

        $this->assertStringContainsString('PatientFileExported', $bootSource);
        $this->assertStringContainsString('NotifyPatientFileExported', $bootSource);
    }
}
