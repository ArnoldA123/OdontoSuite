<?php

namespace Tests\Feature\Api;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * RED test (slice 02, T-02.1, T-02.2, T-02.12) — BF-007, BF-008.
 *
 * Acceptance via FormRequest::rules() inspection + Validator facade:
 *  - StoreAppointmentRequest::rules() includes procedure_id, treatment_plan_id, branch_id, ends_at
 *  - UpdateAppointmentRequest::rules() includes ends_at with after:starts_at
 *  - Status enum canonical is in_consultation (NOT in_progress)
 *
 * Tests use Validator::make() with stub data; DB-touching tests are in tests that
 * require RefreshDatabase (CI MySQL only — see AGENTS.md §6 for pre-existing
 * SQLite MODIFY COLUMN tech debt). This file targets CI / MySQL only.
 *
 * Tests skip on SQLite in-memory since the appointments_status_enum migration
 * breaks SQLite (AGENTS.md §6).
 */
class AppointmentValidationTest extends TestCase
{
    public function test_store_appointment_rules_include_required_fields(): void
    {
        $rules = (new StoreAppointmentRequest())->rules();

        // New optional IDs (BF-007)
        $this->assertArrayHasKey('procedure_id', $rules);
        $this->assertArrayHasKey('treatment_plan_id', $rules);
        $this->assertArrayHasKey('branch_id', $rules);
        $this->assertArrayHasKey('ends_at', $rules);

        // nullable + exists binding
        $this->assertStringContainsString('nullable', $rules['procedure_id']);
        $this->assertStringContainsString('procedure_catalog', $rules['procedure_id']);
        $this->assertStringContainsString('nullable', $rules['treatment_plan_id']);
        $this->assertStringContainsString('treatment_plans', $rules['treatment_plan_id']);
        $this->assertStringContainsString('nullable', $rules['branch_id']);
        $this->assertStringContainsString('branches', $rules['branch_id']);
    }

    public function test_store_appointment_status_canonical_value(): void
    {
        $rules = (new StoreAppointmentRequest())->rules();
        $statusRule = $rules['status'];

        // 'in_consultation' is canonical (post-migration)
        $this->assertStringContainsString('in_consultation', $statusRule);
        // 'in_progress' (legacy) should NOT be in the canonical list
        $this->assertStringNotContainsString('in_progress', $statusRule);
    }

    public function test_update_appointment_rules_include_ends_at_after_scheduled_at(): void
    {
        $rules = (new UpdateAppointmentRequest())->rules();

        $this->assertArrayHasKey('ends_at', $rules);
        $this->assertStringContainsString('nullable', $rules['ends_at']);
        $this->assertStringContainsString('date', $rules['ends_at']);
        // scheduled_at is the actual field name; rule `after:scheduled_at` enforces ends_at > scheduled_at.
        $this->assertStringContainsString('after:scheduled_at', $rules['ends_at']);
    }

    public function test_update_appointment_validator_rejects_ends_at_before_starts_at(): void
    {
        $rules = (new UpdateAppointmentRequest())->rules();

        $validator = Validator::make(
            [
                'scheduled_at' => now()->addHour()->toDateTimeString(),
                'ends_at' => now()->subHour()->toDateTimeString(),
            ],
            [
                'scheduled_at' => $rules['scheduled_at'],
                'ends_at' => $rules['ends_at'],
            ]
        );
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('ends_at'));
    }

    public function test_update_appointment_validator_accepts_ends_at_after_starts_at(): void
    {
        $rules = (new UpdateAppointmentRequest())->rules();

        $validator = Validator::make(
            [
                'scheduled_at' => now()->addHour()->toDateTimeString(),
                'ends_at' => now()->addHours(3)->toDateTimeString(),
            ],
            [
                'scheduled_at' => $rules['scheduled_at'],
                'ends_at' => $rules['ends_at'],
            ]
        );
        $this->assertFalse($validator->fails());
    }

    public function test_store_appointment_status_validator_accepts_in_consultation(): void
    {
        $rules = (new StoreAppointmentRequest())->rules();

        $validator = Validator::make(
            ['status' => 'in_consultation'],
            ['status' => $rules['status']]
        );
        $this->assertFalse($validator->fails());

        $validator2 = Validator::make(
            ['status' => 'in_progress'],
            ['status' => $rules['status']]
        );
        $this->assertTrue($validator2->fails(), 'in_progress should be rejected as non-canonical');
    }
}
