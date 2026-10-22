<?php

namespace App\Authorization;

/**
 * The six desks of F3 (#283 S1).
 *
 * F0 (#280, S3) fixed the vocabulary of user groups: recepción, clínico,
 * asistente, laboratorio, finanzas y administración. This class turns that
 * vocabulary into an explicit matrix: every `users.role` sits at exactly one
 * desk, and a desk holds exactly the union of the permissions its roles hold, so
 * the matrix is derived from `Permissions` and can never drift from it.
 *
 * A desk is where the work happens, not a new authorization unit: routes keep
 * declaring `permission:verbo.recurso`. Tests and documentation read this class
 * to answer "who sits at this desk and what can they do".
 */
final class Desks
{
    public const RECEPTION = 'recepcion';

    public const CLINICIAN = 'clinico';

    public const ASSISTANT = 'asistente';

    public const LABORATORY = 'laboratorio';

    public const FINANCE = 'finanzas';

    public const ADMINISTRATION = 'administrador';

    /**
     * desk => roles that sit at it. The six sets partition `Permissions::ROLES`.
     *
     * @var array<string, list<string>>
     */
    private const DESK_ROLES = [
        self::RECEPTION => ['recepcionista'],
        self::CLINICIAN => ['odontologo', 'implantologo'],
        self::ASSISTANT => ['asistente'],
        self::LABORATORY => ['tecnico_dental'],
        self::FINANCE => ['finanzas'],
        self::ADMINISTRATION => ['administrador'],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::DESK_ROLES);
    }

    /**
     * @return list<string>
     */
    public static function roles(string $desk): array
    {
        return self::DESK_ROLES[$desk] ?? [];
    }

    /**
     * Permissions the desk holds: the union over its roles.
     *
     * @return list<string>
     */
    public static function permissions(string $desk): array
    {
        $permissions = [];

        foreach (self::roles($desk) as $role) {
            foreach (Permissions::forRole($role) as $permission) {
                $permissions[$permission] = true;
            }
        }

        return array_keys($permissions);
    }

    /**
     * @return list<string>
     */
    public static function desksForRole(string $role): array
    {
        return array_keys(array_filter(
            self::DESK_ROLES,
            static fn (array $roles): bool => in_array($role, $roles, true),
        ));
    }

    /**
     * Catalog permissions no desk holds. The guard test requires this to be
     * empty: a permission nobody can exercise is dead surface.
     *
     * @return list<string>
     */
    public static function uncoveredPermissions(): array
    {
        $covered = [];

        foreach (self::all() as $desk) {
            foreach (self::permissions($desk) as $permission) {
                $covered[$permission] = true;
            }
        }

        return array_values(array_diff(Permissions::all(), array_keys($covered)));
    }

    /**
     * permission => desks that hold it. One entry per catalog permission.
     *
     * @return array<string, list<string>>
     */
    public static function permissionMatrix(): array
    {
        $matrix = [];

        foreach (self::all() as $desk) {
            foreach (self::permissions($desk) as $permission) {
                $matrix[$permission][] = $desk;
            }
        }

        return $matrix;
    }
}
