<?php

namespace Tests\Unit\Authorization;

use App\Authorization\BroadcastChannels;
use App\Authorization\Permissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #378/#384 — the private channel handshake and the SPA fan-out share one rule.
 *
 * `BroadcastingAuthController` used to keep its own role lists, so the server
 * matrix could drift from `Permissions` and from the channels the shell asks
 * for. These guards cross the three artifacts: the channel => permission map,
 * the catalog that turns it into role grants, and the SPA map that gates each
 * subscription with the same permission name.
 */
class BroadcastChannelAuthorizationTest extends TestCase
{
    private static function source(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/'.$relative;
        $source = file_get_contents($path);

        self::assertNotFalse($source, "{$relative} must exist");

        return $source;
    }

    /**
     * @return array<string, string> channel => permission, as the SPA declares it
     */
    private function spaChannels(): array
    {
        preg_match_all(
            "/'?([a-z-]+)'?:\\s*'([a-z_]+(?:\\.[a-z_]+)+)'/",
            self::source('resources/js/utils/broadcastChannels.js'),
            $matches,
            PREG_SET_ORDER,
        );

        $map = [];

        foreach ($matches as $match) {
            $map[$match[1]] = $match[2];
        }

        return $map;
    }

    /**
     * @return list<string> the channels the shell actually subscribes to
     */
    private function shellChannels(): array
    {
        preg_match_all(
            "/subscribe\\('([a-z-]+)'/",
            self::source('resources/js/composables/useWebSocketNotifications.js'),
            $matches,
        );

        return array_values(array_unique($matches[1]));
    }

    #[Test]
    public function every_channel_resolves_to_a_catalog_permission(): void
    {
        $this->assertNotEmpty(BroadcastChannels::permissions(), 'The channel map must not be empty.');

        foreach (BroadcastChannels::permissions() as $channel => $permission) {
            $this->assertTrue(
                Permissions::exists($permission),
                "Channel [{$channel}] maps to unknown permission [{$permission}].",
            );
        }
    }

    #[Test]
    public function the_handshake_authorizes_exactly_the_roles_holding_its_permission(): void
    {
        foreach (BroadcastChannels::permissions() as $channel => $permission) {
            foreach (Permissions::ROLES as $role) {
                $this->assertSame(
                    Permissions::roleHas($role, $permission),
                    BroadcastChannels::allows($channel, $role, 7),
                    "Channel [{$channel}] and permission [{$permission}] disagree for role [{$role}].",
                );
            }
        }
    }

    #[Test]
    public function the_dynamic_channels_follow_their_desk_permission(): void
    {
        $session = ['administrador', 'finanzas', 'recepcionista'];
        $appointment = [
            'administrador', 'recepcionista', 'odontologo',
            'implantologo', 'tecnico_dental', 'asistente',
        ];

        foreach (Permissions::ROLES as $role) {
            $this->assertSame(
                in_array($role, $session, true),
                BroadcastChannels::allows('cash-session.9', $role, 7),
                "cash-session.9 must follow the cash register grant for [{$role}].",
            );
            $this->assertSame(
                in_array($role, $session, true),
                BroadcastChannels::allows('cash-register.global', $role, 7),
                "cash-register.global must follow the cash register grant for [{$role}].",
            );
            $this->assertSame(
                in_array($role, $appointment, true),
                BroadcastChannels::allows('appointment.9', $role, 7),
                "appointment.9 must follow the appointment grant for [{$role}].",
            );
        }
    }

    #[Test]
    public function the_user_channels_accept_only_their_own_subscriber(): void
    {
        foreach (['user.7', 'App.Models.User.7'] as $channel) {
            $this->assertTrue(BroadcastChannels::allows($channel, 'odontologo', 7));
            $this->assertFalse(BroadcastChannels::allows($channel, 'odontologo', 8));
            $this->assertFalse(BroadcastChannels::allows($channel, 'odontologo', null));
        }
    }

    #[Test]
    public function unknown_channels_resolve_to_no_permission(): void
    {
        $this->assertNull(BroadcastChannels::permissionFor('unknown-channel'));
        $this->assertFalse(BroadcastChannels::allows('unknown-channel', 'administrador', 1));
    }

    #[Test]
    public function the_spa_gates_the_shell_channels_with_the_server_permission(): void
    {
        $server = BroadcastChannels::permissions();
        $spa = $this->spaChannels();

        $this->assertNotEmpty($spa, 'broadcastChannels.js must declare the SPA channel map.');

        foreach ($spa as $channel => $permission) {
            $this->assertArrayHasKey(
                $channel,
                $server,
                "The SPA subscribes to [{$channel}], which the server does not authorize.",
            );
            $this->assertSame(
                $server[$channel],
                $permission,
                "The SPA gates [{$channel}] with [{$permission}] while the server uses [{$server[$channel]}].",
            );
        }
    }

    #[Test]
    public function the_shell_subscribes_to_exactly_the_gated_channels(): void
    {
        $this->assertSame(
            array_keys($this->spaChannels()),
            $this->shellChannels(),
            'Every shell subscription must go through the permission-gated map.',
        );
        $this->assertStringNotContainsString(
            "privateChannel('",
            self::source('resources/js/composables/useWebSocketNotifications.js'),
            'The fan-out must call privateChannel only through the gated subscribe() helper.',
        );
    }
}
