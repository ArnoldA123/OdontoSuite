<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Transaction;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED test (verify-correction slice) for routes missing in slice 01:
 *  - POST /api/transactions/{transaction}/void
 *  - POST /api/transactions/{transaction}/receipt
 *
 * The TransactionController already implements void() and generateReceipt(),
 * and TransactionService already exposes voidTransaction() and generateReceipt().
 * These tests assert the ROUTES are wired so the implementations become reachable.
 *
 * @group mysql
 */
class TransactionVoidAndReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function finanzasUser(): User
    {
        return User::factory()->create([
            'role' => 'finanzas',
            'is_active' => true,
        ]);
    }

    protected function openSessionFor(User $user): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    protected function completedTransaction(CashRegisterSession $session): Transaction
    {
        $patient = Patient::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();

        return Transaction::create([
            'patient_id' => $patient->id,
            'payment_method_id' => $paymentMethod->id,
            'cash_register_session_id' => $session->id,
            'created_by' => $session->user_id,
            'transaction_number' => 'TXN-' . now()->format('Ymd') . '-0001',
            'type' => 'payment',
            'amount' => 150.00,
            'subtotal' => 150.00,
            'discount_amount' => 0,
            'commission_amount' => 0,
            'description' => 'Test transaction',
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * RED: route /api/transactions/{transaction}/void must be registered
     * and return 200 with the voided transaction (status=voided).
     */
    public function test_post_void_route_returns_200_and_marks_transaction_voided(): void
    {
        $user = $this->finanzasUser();
        $session = $this->openSessionFor($user);
        $transaction = $this->completedTransaction($session);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Test void reason',
            ]);

        $response->assertOk();

        $payload = $response->json();
        $this->assertSame('voided', $payload['data']['status'] ?? null);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'voided',
        ]);
    }

    /**
     * RED: route /api/transactions/{transaction}/void rejects missing reason (422).
     */
    public function test_post_void_route_requires_reason(): void
    {
        $user = $this->finanzasUser();
        $session = $this->openSessionFor($user);
        $transaction = $this->completedTransaction($session);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", []);

        $response->assertStatus(422);
    }

    /**
     * RED: route /api/transactions/{transaction}/receipt must be registered
     * and return a PDF (Content-Type: application/pdf).
     */
    public function test_post_receipt_route_returns_pdf_content_type(): void
    {
        $user = $this->finanzasUser();
        $session = $this->openSessionFor($user);
        $transaction = $this->completedTransaction($session);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/receipt");

        $response->assertOk();

        $contentType = $response->headers->get('Content-Type', '');
        $this->assertStringContainsString('application/pdf', $contentType);

        $body = $response->getContent();
        $this->assertNotEmpty($body);
        $this->assertStringStartsWith('%PDF', $body);
    }
}