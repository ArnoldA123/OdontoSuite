<?php

namespace Tests\Feature\Api;

use App\Events\AppointmentCheckedIn;
use App\Events\PaymentReceived;
use App\Models\Appointment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Defect #28: the private channels emitted by AppointmentCheckedIn and
 * PaymentReceived were double-prefixed (`private-private-*`) and the custom
 * authorization handler did not know them, so subscribers got a 404.
 *
 * This test pins the effective authority
 * (App\Http\Controllers\Api\BroadcastingAuthController) to authorize the
 * de-doubled channel names with the same role rules declared in
 * routes/channels.php.
 */
class BroadcastingAuthChannelsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function authRequest(User $user, string $channel): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => $channel,
        ]);
    }

    public function test_administrador_is_authorized_on_appointment_and_cash_register(): void
    {
        $user = $this->userWithRole('administrador');

        $this->authRequest($user, 'private-appointment.1')
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->authRequest($user, 'private-cash-register.2')
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_odontologo_is_authorized_on_appointment_but_forbidden_on_cash_register(): void
    {
        $user = $this->userWithRole('odontologo');

        $this->authRequest($user, 'private-appointment.1')->assertOk();
        $this->authRequest($user, 'private-cash-register.2')->assertForbidden();
    }

    public function test_finanzas_is_authorized_on_cash_register_but_forbidden_on_appointment(): void
    {
        $user = $this->userWithRole('finanzas');

        $this->authRequest($user, 'private-cash-register.2')->assertOk();
        $this->authRequest($user, 'private-appointment.1')->assertForbidden();
    }

    public function test_recepcionista_is_authorized_on_both_channels(): void
    {
        $user = $this->userWithRole('recepcionista');

        $this->authRequest($user, 'private-appointment.1')->assertOk();
        $this->authRequest($user, 'private-cash-register.2')->assertOk();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-appointment.1',
        ])->assertUnauthorized();
    }

    public function test_unknown_channel_returns_404(): void
    {
        $user = $this->userWithRole('administrador');

        $this->authRequest($user, 'private-nope.1')->assertNotFound();
    }

    public function test_events_emit_de_doubled_private_channel_names(): void
    {
        $appointment = new Appointment();
        $appointment->id = 1;
        $appointmentChannel = (new AppointmentCheckedIn($appointment))->broadcastOn()[0];
        // PrivateChannel prepends `private-`; the wire name must carry it
        // exactly once (the defect produced `private-private-appointment.1`).
        $this->assertSame('private-appointment.1', $appointmentChannel->name);
        $this->assertStringNotContainsString('private-private-', $appointmentChannel->name);

        $transaction = new Transaction();
        $transaction->branch_id = 2;
        $paymentChannel = (new PaymentReceived($transaction))->broadcastOn();
        $this->assertSame('private-cash-register.2', $paymentChannel->name);
        $this->assertStringNotContainsString('private-private-', $paymentChannel->name);
    }
}
