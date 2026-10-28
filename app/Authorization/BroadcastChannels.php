<?php

namespace App\Authorization;

/**
 * #378/#384 — the private channel handshake and the SPA subscription share one
 * rule: the permission that already guards the desk a channel publishes.
 *
 * `BroadcastingAuthController` used to carry its own role lists next to
 * `Permissions`, so the server matrix and the client's subscriptions could
 * drift apart. Now every channel resolves to a catalog permission and both
 * sides read the same grant: the controller through `Permissions::roleHas()`,
 * the shell through the permission list it receives at login.
 */
final class BroadcastChannels
{
    /** The subscriber's own channels: `user.{id}` and `App.Models.User.{id}`. */
    public const SELF = 'self';

    /**
     * Static domain channel => permission that grants read access to it.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'appointments' => Permissions::MANAGE_APPOINTMENT,
        'reminders' => Permissions::MANAGE_REMINDER,
        'medical-records' => Permissions::MANAGE_MEDICAL_RECORD,
        'specialty-records' => Permissions::MANAGE_SPECIALTY_RECORD,
        'treatment-plans' => Permissions::MANAGE_TREATMENT_PLAN,
        'cash-register' => Permissions::VIEW_CASH_REGISTER,
        'transactions' => Permissions::MANAGE_TRANSACTION,
        'procedure-catalog' => Permissions::VIEW_PROCEDURE_CATALOG,
        'users' => Permissions::MANAGE_USER,
        'dashboard-updates' => Permissions::VIEW_DASHBOARD,
        'quotations' => Permissions::VIEW_QUOTATION,
        'patients' => Permissions::VIEW_PATIENT,
        'lab-cases' => Permissions::VIEW_LAB_CASE,
    ];

    /**
     * @return array<string, string>
     */
    public static function permissions(): array
    {
        return self::PERMISSIONS;
    }

    /**
     * Resolve a channel base name (the `private-` prefix already stripped).
     *
     * @return string|null a catalog permission, `self`, or null when unknown
     */
    public static function permissionFor(string $channel): ?string
    {
        if (preg_match('/^(?:App\.Models\.User\.|user\.)\d+$/', $channel)) {
            return self::SELF;
        }

        if (preg_match('/^(?:cash-session\.\d+|cash-register\.(?:\d+|global))$/', $channel)) {
            return Permissions::VIEW_CASH_REGISTER;
        }

        if (preg_match('/^appointment\.\d+$/', $channel)) {
            return Permissions::MANAGE_APPOINTMENT;
        }

        return self::PERMISSIONS[$channel] ?? null;
    }

    /**
     * Whether the given session may subscribe to the channel.
     */
    public static function allows(string $channel, ?string $role, ?int $userId = null): bool
    {
        $permission = self::permissionFor($channel);

        if ($permission === null) {
            return false;
        }

        if ($permission === self::SELF) {
            preg_match('/^(?:App\.Models\.User\.|user\.)(\d+)$/', $channel, $matches);

            return $userId !== null && (int) $matches[1] === (int) $userId;
        }

        return Permissions::roleHas($role, $permission);
    }
}
