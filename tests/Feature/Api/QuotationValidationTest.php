<?php

namespace Tests\Feature\Api;

use App\Http\Requests\StoreQuotationRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * RED test (slice 02, T-02.5, T-02.7) — BF-009.
 */
class QuotationValidationTest extends TestCase
{
    public function test_store_quotation_rules_include_procedure_id_and_payment_method_id(): void
    {
        $rules = (new StoreQuotationRequest())->rules();

        $this->assertArrayHasKey('procedure_id', $rules);
        $this->assertArrayHasKey('payment_method_id', $rules);

        $this->assertStringContainsString('nullable', $rules['procedure_id']);
        $this->assertStringContainsString('procedure_catalog', $rules['procedure_id']);
        $this->assertStringContainsString('nullable', $rules['payment_method_id']);
        $this->assertStringContainsString('payment_methods', $rules['payment_method_id']);
    }

    public function test_patient_id_messages_consistent_with_sometimes_nullable(): void
    {
        $messages = (new StoreQuotationRequest())->messages();

        // per BF-009: patient_id is sometimes|nullable, so a "required" message
        // is inconsistent. After slice 02 that key must be removed.
        $this->assertArrayNotHasKey(
            'patient_id.required',
            $messages,
            'patient_id.required message exists despite sometimes|nullable rule (BF-009)'
        );
    }

    public function test_patient_id_optional_when_absent(): void
    {
        $rules = (new StoreQuotationRequest())->rules();
        $validator = Validator::make(
            ['subtotal' => 100],
            ['patient_id' => $rules['patient_id'], 'subtotal' => $rules['subtotal']]
        );
        $this->assertFalse(
            $validator->errors()->has('patient_id'),
            'patient_id is sometimes|nullable and must NOT fail when absent'
        );
    }
}
