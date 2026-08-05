<?php

namespace Tests\Feature\Api;

use App\Http\Requests\CloseCashRegisterRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreCashMovementRequest;
use App\Http\Requests\StoreTreatmentPlanRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * RED test (slice 02, T-02.6, T-02.8, T-02.9, T-02.11) — API-014, API-045.
 */
class CashRegisterValidationTest extends TestCase
{
    public function test_close_cash_rules_reject_zero_closing_amount(): void
    {
        $rules = (new CloseCashRegisterRequest())->rules();

        // Original rule: 'min:0' allows 0. After slice 02 must reject 0.
        $validator = Validator::make(
            ['closing_amount' => 0],
            ['closing_amount' => $rules['closing_amount']]
        );
        $this->assertTrue(
            $validator->fails(),
            'closing_amount=0 must fail validation after API-014 fix'
        );
        $this->assertTrue($validator->errors()->has('closing_amount'));
    }

    public function test_close_cash_rules_accept_positive_closing_amount(): void
    {
        $rules = (new CloseCashRegisterRequest())->rules();
        $validator = Validator::make(
            ['closing_amount' => 100.50],
            ['closing_amount' => $rules['closing_amount']]
        );
        $this->assertFalse($validator->fails());
    }

    public function test_store_transaction_rules_include_payment_method_id(): void
    {
        $rules = (new StoreTransactionRequest())->rules();
        $this->assertArrayHasKey('payment_method_id', $rules);
    }

    public function test_store_cash_movement_rules_exist_with_branch_id_and_concept(): void
    {
        // Per the spec T-02.11, StoreCashMovementRequest must exist with:
        // - branch_id (nullable, exists:branches,id)
        // - concept (in:opening_balance,sale_refund,withdrawal,deposit,adjustment)
        $this->assertTrue(class_exists(StoreCashMovementRequest::class),
            'StoreCashMovementRequest class must exist (T-02.11)');

        $rules = (new StoreCashMovementRequest())->rules();
        $this->assertArrayHasKey('branch_id', $rules);
        $this->assertArrayHasKey('concept', $rules);

        $this->assertStringContainsString('nullable', $rules['branch_id']);
        $this->assertStringContainsString('branches', $rules['branch_id']);

        $this->assertStringContainsString('opening_balance', $rules['concept']);
        $this->assertStringContainsString('sale_refund', $rules['concept']);
        $this->assertStringContainsString('withdrawal', $rules['concept']);
        $this->assertStringContainsString('deposit', $rules['concept']);
        $this->assertStringContainsString('adjustment', $rules['concept']);
    }

    public function test_store_treatment_plan_rules_include_branch_id(): void
    {
        $rules = (new StoreTreatmentPlanRequest())->rules();
        $this->assertArrayHasKey('branch_id', $rules);
        $this->assertStringContainsString('nullable', $rules['branch_id']);
        $this->assertStringContainsString('branches', $rules['branch_id']);
    }
}
