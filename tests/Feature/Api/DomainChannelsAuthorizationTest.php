<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Issue #249 — every domain channel carries domain payloads, so its handshake
 * must verify the role that already has read access in `routes/api.php`.
 *
 * The effective authority is App\Http\Controllers\Api\BroadcastingAuthController
 * (the project does not call Broadcast::routes()); `routes/channels.php` mirrors
 * the same rules as documentation. A role outside the allowlist gets 403, not
 * 404: the channel exists and the subscriber is not entitled to it.
 */
class DomainChannelsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The seven roles the application knows about.
     *
     * @var string[]
     */
    private const ROLES = [
        'administrador', 'recepcionista', 'odontologo', 'implantologo',
        'tecnico_dental', 'asistente', 'finanzas',
    ];

    /**
     * Channel base name => [channel, roles with read access to its domain in
     * `routes/api.php`].
     *
     * @return array<string, array{string, string[]}>
     */
    public static function channels(): array
    {
        $appointmentRoles = [
            'administrador', 'recepcionista', 'odontologo',
            'implantologo', 'tecnico_dental', 'asistente',
        ];

        $map = [
            'appointments' => $appointmentRoles,
            'reminders' => $appointmentRoles,
            'medical-records' => [
                'administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente',
            ],
            'specialty-records' => [
                'administrador', 'odontologo', 'implantologo', 'tecnico_dental',
            ],
            'treatment-plans' => [
                'administrador', 'odontologo', 'implantologo', 'tecnico_dental',
            ],
            'cash-register' => ['administrador', 'finanzas', 'recepcionista'],
            'transactions' => ['administrador', 'finanzas', 'recepcionista'],
            'procedure-catalog' => self::ROLES,
            'users' => ['administrador'],
        ];

        $data = [];

        foreach ($map as $channel => $roles) {
            $data[$channel] = [$channel, $roles];
        }

        return $data;
    }

    /**
     * @test
     *
     * @dataProvider channels
     */
    public function channel_authorizes_exactly_the_roles_with_read_access(
        string $channel,
        array $allowedRoles
    ): void {
        foreach (self::ROLES as $role) {
            $response = $this->authRequest($this->userWithRole($role), $channel);

            if (in_array($role, $allowedRoles, true)) {
                $response->assertOk()->assertJsonStructure(['auth']);
            } else {
                $response->assertForbidden();
            }
        }
    }

    /**
     * @test
     *
     * @dataProvider channels
     */
    public function channel_rejects_an_unauthenticated_subscriber(string $channel, array $allowedRoles): void
    {
        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => $this->wireName($channel),
        ])->assertUnauthorized();
    }

    /**
     * @test
     *
     * @dataProvider channels
     */
    public function channel_rejects_a_role_outside_the_allowlist(string $channel, array $allowedRoles): void
    {
        $this->authRequest($this->userWithRole('auditor'), $channel)->assertForbidden();
    }

    /**
     * @test
     */
    public function the_user_channel_accepts_only_its_own_subscriber(): void
    {
        $owner = $this->userWithRole('odontologo');
        $other = $this->userWithRole('odontologo');

        $this->authRequest($owner, "user.{$owner->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->authRequest($other, "user.{$owner->id}")->assertForbidden();
        $this->authRequest($owner, "user.{$other->id}")->assertForbidden();
    }

    private function wireName(string $channel): string
    {
        return str_starts_with($channel, 'user.') ? $channel : 'private-'.$channel;
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function authRequest(User $user, string $channel): TestResponse
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => $this->wireName($channel),
        ]);
    }
}
