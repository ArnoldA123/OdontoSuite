<?php

namespace Tests\Unit\Models;

use App\Models\ReminderSchedule;
use App\Services\ReminderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Slice 07a (reminder-schedule-write-contract) regression guard.
 *
 * Pure unit test (no DB, no RefreshDatabase) so it runs on SQLite without
 * the pre-existing `transactions.type` DROP COLUMN tech-debt. The CI MySQL
 * run is the canonical oracle for the running service contract.
 *
 * The contract: `ReminderSchedule::$fillable` MUST match the union of
 * columns declared by the two migrations touching `reminder_schedules`
 * (2025_09_20 original + 2026_08_05 channel/error_message). The service
 * MUST write `hours_before` and MUST NOT write the phantom `type` or
 * `anticipation_hours`. `scopeOfType()` MUST be deleted (zero callers).
 *
 * Rollback: delete this single file. No production code, no migration,
 * no seeder is touched.
 */
class ReminderScheduleFillableContractTest extends TestCase
{
    private const EXPECTED_FILLABLE = [
        'appointment_id',
        'reminder_template_id',
        'hours_before',
        'scheduled_at',
        'sent_at',
        'channel',
        'status',
        'error_message',
    ];

    private const PHANTOM_FILLABLE = ['type', 'anticipation_hours'];

    private const SERVICE_FILE = __DIR__ . '/../../../app/Services/ReminderService.php';
    private const MODEL_FILE = __DIR__ . '/../../../app/Models/ReminderSchedule.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertFileExists(self::SERVICE_FILE, 'ReminderService.php must exist for the field-contract test.');
        $this->assertFileExists(self::MODEL_FILE, 'ReminderSchedule.php must exist for the field-contract test.');
    }

    /** @return array<int, string> */
    private function fillable(): array
    {
        $defaults = (new ReflectionClass(ReminderSchedule::class))->getDefaultProperties();
        $this->assertArrayHasKey('fillable', $defaults, 'ReminderSchedule must declare $fillable.');
        return $defaults['fillable'] ?? [];
    }

    private function modelSourceRaw(): string
    {
        $source = file_get_contents(self::MODEL_FILE);
        $this->assertIsString($source, 'ReminderSchedule.php must be readable.');
        return $source;
    }

    private function serviceSourceRaw(): string
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertIsString($source, 'ReminderService.php must be readable.');
        return $source;
    }

    // -------------------------------------------------------------------
    // $fillable contract
    // -------------------------------------------------------------------

    /** @test */
    public function test_fillable_matches_canonical_schema_columns(): void
    {
        $declared = $this->fillable();

        $this->assertEqualsCanonicalizing(
            self::EXPECTED_FILLABLE,
            $declared,
            sprintf(
                "ReminderSchedule::\$fillable must match the canonical columns. Expected: [%s]. Actual: [%s].",
                implode(', ', self::EXPECTED_FILLABLE),
                implode(', ', $declared)
            )
        );
    }

    /** @test */
    public function test_fillable_contains_hours_before_and_no_phantoms(): void
    {
        $declared = $this->fillable();

        $this->assertContains('hours_before', $declared, 'ReminderSchedule::$fillable MUST contain `hours_before` (canonical).');
        $this->assertNotContains('type', $declared, 'ReminderSchedule::$fillable MUST NOT contain `type` (no migration ever added it).');
        $this->assertNotContains('anticipation_hours', $declared, 'ReminderSchedule::$fillable MUST NOT contain `anticipation_hours` (no migration ever added it).');

        $phantoms = array_values(array_intersect(self::PHANTOM_FILLABLE, $declared));
        $this->assertSame([], $phantoms, sprintf('ReminderSchedule::$fillable MUST declare zero phantom columns. Found: [%s].', implode(', ', $phantoms)));
    }

    // -------------------------------------------------------------------
    // ReminderService source contract
    // -------------------------------------------------------------------

    /** @test */
    public function test_service_writes_hours_before_only(): void
    {
        $source = $this->serviceSourceRaw();

        $this->assertStringContainsString("'hours_before' =>", $source, 'ReminderService MUST write the canonical `hours_before` column.');
        $this->assertStringNotContainsString("'anticipation_hours' =>", $source, 'ReminderService MUST NOT write the phantom `anticipation_hours` column. Use `hours_before` instead.');
        $this->assertStringNotContainsString("'type' =>", $source, 'ReminderSchedule no longer carries a `type` column; the redundant `type` write MUST be removed from ReminderService.');
    }

    // -------------------------------------------------------------------
    // scopeOfType removal
    // -------------------------------------------------------------------

    /** @test */
    public function test_scope_of_type_is_removed(): void
    {
        $this->assertFalse(
            method_exists(ReminderSchedule::class, 'scopeOfType'),
            'ReminderSchedule::scopeOfType queried the removed `type` column and MUST be deleted (zero callers verified by grep).'
        );
        $this->assertStringNotContainsString(
            'scopeOfType',
            $this->modelSourceRaw(),
            'ReminderSchedule.php source MUST NOT reference `scopeOfType` (the scope was tied to the removed `type` column).'
        );
    }

    // -------------------------------------------------------------------
    // Service contract sanity check
    // -------------------------------------------------------------------

    /** @test */
    public function test_service_exposes_required_methods(): void
    {
        $this->assertTrue(method_exists(ReminderService::class, 'scheduleReminder'), 'ReminderService::scheduleReminder() must exist.');
        $this->assertTrue(method_exists(ReminderService::class, 'createCustomReminder'), 'ReminderService::createCustomReminder() must exist.');
        $this->assertTrue(method_exists(ReminderService::class, 'sendImmediateReminder'), 'ReminderService::sendImmediateReminder() must exist.');
    }
}
