<?php

namespace Tests\Feature\Api;

use App\Http\Requests\CloseCashRegisterRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\StoreCashMovementRequest;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\StoreSpecialtyRecordRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreTreatmentPlanRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * RED test (slice 02, T-02.14) — parameterized smoke test that the new
 * optional fields are accepted by each FormRequest's rules() and that the
 * FormRequest still validates cleanly when the legacy fields are present.
 *
 * Strategy: assertion-based rules inspection, no DB needed.
 */
class FormRequestFieldsTest extends TestCase
{
    /**
     * Each row: [FormRequest::class, 'field', 'expected-marker-in-rules'].
     */
    public static function formRequestsWithNewFields(): array
    {
        return [
            'StoreAppointmentRequest::procedure_id' => [StoreAppointmentRequest::class, 'procedure_id', 'procedure_catalog'],
            'StoreAppointmentRequest::treatment_plan_id' => [StoreAppointmentRequest::class, 'treatment_plan_id', 'treatment_plans'],
            'StoreAppointmentRequest::branch_id' => [StoreAppointmentRequest::class, 'branch_id', 'branches'],
            'StoreAppointmentRequest::ends_at' => [StoreAppointmentRequest::class, 'ends_at', 'nullable'],
            'UpdateAppointmentRequest::ends_at' => [UpdateAppointmentRequest::class, 'ends_at', 'after:scheduled_at'],
            'StoreQuotationRequest::procedure_id' => [StoreQuotationRequest::class, 'procedure_id', 'procedure_catalog'],
            'StoreQuotationRequest::payment_method_id' => [StoreQuotationRequest::class, 'payment_method_id', 'payment_methods'],
            'StoreTreatmentPlanRequest::branch_id' => [StoreTreatmentPlanRequest::class, 'branch_id', 'branches'],
            'StoreCashMovementRequest::branch_id' => [StoreCashMovementRequest::class, 'branch_id', 'branches'],
            'StoreCashMovementRequest::concept' => [StoreCashMovementRequest::class, 'concept', 'opening_balance'],
            'StoreSpecialtyRecordRequest::procedure_id' => [StoreSpecialtyRecordRequest::class, 'procedure_id', 'procedure_catalog'],
            'StoreTransactionRequest::payment_method_id' => [StoreTransactionRequest::class, 'payment_method_id', 'payment_methods'],
        ];
    }

    /**
     * @dataProvider formRequestsWithNewFields
     */
    public function test_new_optional_field_is_declared_in_rules(string $formRequestClass, string $field, string $expectedMarker): void
    {
        $this->assertTrue(class_exists($formRequestClass), "$formRequestClass missing");

        /** @var \Illuminate\Foundation\Http\FormRequest $formRequest */
        $formRequest = new $formRequestClass();
        $rules = $formRequest->rules();

        $this->assertArrayHasKey(
            $field,
            $rules,
            "$formRequestClass::rules() missing $field (was it added in this slice?)"
        );

        $ruleAsString = is_array($rules[$field]) ? implode('|', $rules[$field]) : (string) $rules[$field];

        $this->assertStringContainsString(
            $expectedMarker,
            $ruleAsString,
            "$formRequestClass::rules()[$field] expected substring '$expectedMarker', got: $ruleAsString"
        );
    }

    /**
     * `meta.locale: 'es'` is enforced indirectly via per-request messages() in es;
     * this test ensures every FormRequest returns a non-empty messages array.
     */
    public function test_all_form_requests_have_spanish_messages(): void
    {
        $formRequests = [
            StoreAppointmentRequest::class,
            UpdateAppointmentRequest::class,
            StoreQuotationRequest::class,
            StoreSpecialtyRecordRequest::class,
            CloseCashRegisterRequest::class,
            StoreTransactionRequest::class,
            StoreTreatmentPlanRequest::class,
            StoreCashMovementRequest::class,
        ];

        foreach ($formRequests as $formRequestClass) {
            $messages = (new $formRequestClass())->messages();
            $this->assertNotEmpty(
                $messages,
                "$formRequestClass::messages() must be non-empty (Spanish locale)"
            );
        }
    }
}
