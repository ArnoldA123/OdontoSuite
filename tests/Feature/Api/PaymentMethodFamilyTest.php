<?php

namespace Tests\Feature\Api;

use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #291, S3 — every payment method declares the behaviour family the daily
 * reconciliation groups by, and the closed list is enforced where the method is
 * written.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class PaymentMethodFamilyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    public function test_the_seeded_system_methods_declare_their_family_and_reference_rule(): void
    {
        $this->seed(PaymentMethodSeeder::class);

        $expected = [
            'cash' => ['cash', false],
            'transfer' => ['transfer', true],
            'credit_card' => ['card', true],
            'debit_card' => ['card', true],
            'yape' => ['wallet', true],
        ];

        foreach ($expected as $code => [$type, $requiresReference]) {
            $method = PaymentMethod::where('code', $code)->firstOrFail();

            $this->assertSame($type, $method->type, "El método {$code} declara otra familia.");
            $this->assertSame(
                $requiresReference,
                $method->requires_reference,
                "El método {$code} declara otra regla de referencia."
            );
        }
    }

    public function test_cash_is_the_only_family_that_is_not_electronic(): void
    {
        $this->assertTrue(PaymentMethod::factory()->create(['type' => 'cash'])->isCash());
        $this->assertFalse(PaymentMethod::factory()->create(['type' => 'cash'])->isElectronic());

        foreach (PaymentMethod::ELECTRONIC_TYPES as $type) {
            $method = PaymentMethod::factory()->create(['type' => $type]);

            $this->assertFalse($method->isCash(), "La familia {$type} no es efectivo.");
            $this->assertTrue($method->isElectronic(), "La familia {$type} es un medio electrónico.");
        }
    }

    public function test_an_admin_creates_a_wallet_method_with_its_family(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/payment-methods', [
                'code' => 'plin',
                'name' => 'Plin',
                'type' => 'wallet',
                'requires_reference' => true,
            ])
            ->assertCreated();

        $this->assertSame('wallet', $response->json('data.type'));
        $this->assertTrue($response->json('data.requires_reference'));

        $stored = PaymentMethod::where('code', 'plin')->firstOrFail();

        $this->assertSame('wallet', $stored->type);
        $this->assertTrue($stored->requiresReference());
    }

    public function test_the_family_is_validated_against_the_closed_list(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/payment-methods', [
                'code' => 'pix',
                'name' => 'Pix',
                'type' => 'pix',
            ])
            ->assertStatus(422);

        $response->assertJsonValidationErrors('type');
        $this->assertSame(0, PaymentMethod::where('code', 'pix')->count());
    }

    public function test_a_method_created_without_a_family_defaults_to_cash(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/payment-methods', [
                'code' => 'voucher',
                'name' => 'Vale interno',
            ])
            ->assertCreated();

        $this->assertSame('cash', $response->json('data.type'));
        $this->assertFalse($response->json('data.requires_reference'));
    }

    public function test_the_admin_index_exposes_the_family_and_the_reference_rule(): void
    {
        $method = PaymentMethod::factory()->create([
            'code' => 'yape',
            'name' => 'Yape',
            'type' => 'wallet',
            'requires_reference' => true,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/payment-methods')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $method->id);

        $this->assertSame('wallet', $row['type']);
        $this->assertTrue($row['requires_reference']);
    }

    public function test_the_active_endpoint_exposes_the_family_for_the_cash_dropdown(): void
    {
        $method = PaymentMethod::factory()->create([
            'code' => 'credit_card',
            'name' => 'Tarjeta de crédito',
            'type' => 'card',
            'requires_reference' => true,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/payment-methods/active')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $method->id);

        $this->assertSame('card', $row['type']);
        $this->assertTrue($row['requires_reference']);
    }

    public function test_updating_the_family_is_validated_and_persisted(): void
    {
        $method = PaymentMethod::factory()->create(['type' => 'cash']);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/payment-methods/{$method->id}", [
                'name' => $method->name,
                'type' => 'transfer',
                'requires_reference' => true,
            ])
            ->assertOk();

        $stored = $method->fresh();

        $this->assertSame('transfer', $stored->type);
        $this->assertTrue($stored->requires_reference);
    }
}
