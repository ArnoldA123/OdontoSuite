<?php

namespace Tests\Feature\Api;

use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodAdminContractTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function methodRow(array $response, int $id): array
    {
        return collect($response['data'])->firstWhere('id', $id);
    }

    public function test_active_endpoint_returns_only_active_methods(): void
    {
        $active = PaymentMethod::factory()->create(['is_active' => true]);
        $inactive = PaymentMethod::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/payment-methods/active');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_active_endpoint_is_reachable_for_non_admin_roles(): void
    {
        PaymentMethod::factory()->create(['is_active' => true]);
        $receptionist = User::factory()->create([
            'role' => 'recepcionista',
            'is_active' => true,
        ]);

        $this->actingAs($receptionist, 'sanctum')
            ->getJson('/api/payment-methods/active')
            ->assertOk();
    }

    public function test_active_endpoint_ignores_the_is_active_filter_parameter(): void
    {
        PaymentMethod::factory()->create(['is_active' => true]);
        PaymentMethod::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/payment-methods/active?is_active=false');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_index_exposes_gateway_flag_and_transaction_count(): void
    {
        $withGateway = PaymentMethod::factory()->create([
            'code' => 'with_gateway',
            'gateway_type' => 'mercadopago',
            'gateway_config' => [
                'access_token' => 'TEST-1',
                'public_key' => 'TEST-2',
            ],
        ]);
        $withoutGateway = PaymentMethod::factory()->create(['code' => 'without_gateway']);
        Transaction::factory()->create(['payment_method_id' => $withoutGateway->id]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/payment-methods');

        $response->assertOk();
        $rows = $response->json();

        $withGatewayRow = $this->methodRow($rows, $withGateway->id);
        $withoutGatewayRow = $this->methodRow($rows, $withoutGateway->id);

        $this->assertTrue($withGatewayRow['has_gateway_config']);
        $this->assertFalse($withoutGatewayRow['has_gateway_config']);
        $this->assertSame(0, $withGatewayRow['transactions_count']);
        $this->assertSame(1, $withoutGatewayRow['transactions_count']);
        $this->assertArrayNotHasKey('gateway_config', $withGatewayRow);
    }

    public function test_admin_index_keeps_the_is_active_filter(): void
    {
        PaymentMethod::factory()->create(['is_active' => true]);
        $inactive = PaymentMethod::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/payment-methods?is_active=false');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$inactive->id], $ids);
    }

    public function test_store_ignores_client_supplied_is_system_flag(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/payment-methods', [
                'code' => 'custom',
                'name' => 'Método custom',
                'gateway_type' => 'manual',
                'is_system' => true,
            ]);

        $response->assertCreated();
        $this->assertFalse($response->json('data.is_system'));
        $this->assertDatabaseHas('payment_methods', [
            'code' => 'custom',
            'is_system' => false,
        ]);
    }
}
