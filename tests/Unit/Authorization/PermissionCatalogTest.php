<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Permissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #280 — the catalog and the role matrix are the single source of truth.
 *
 * These tests pin the naming contract, the absence of duplicated grants and the
 * deliberate behavior change: reception no longer closes consultations.
 */
class PermissionCatalogTest extends TestCase
{
    #[Test]
    public function every_permission_uses_verb_dot_resource_naming(): void
    {
        foreach (Permissions::all() as $permission) {
            $this->assertMatchesRegularExpression(
                '/^[a-z_]+(\.[a-z_]+)+$/',
                $permission,
                "Permission [{$permission}] does not follow verb.resource.",
            );
        }
    }

    #[Test]
    public function the_catalog_has_no_duplicate_permissions(): void
    {
        $permissions = Permissions::all();

        $this->assertSame($permissions, array_values(array_unique($permissions)));
    }

    #[Test]
    public function every_granted_role_is_a_known_role(): void
    {
        foreach (Permissions::all() as $permission) {
            foreach (Permissions::rolesFor($permission) as $role) {
                $this->assertContains(
                    $role,
                    Permissions::ROLES,
                    "Permission [{$permission}] grants unknown role [{$role}].",
                );
            }
        }
    }

    #[Test]
    public function every_role_holds_at_least_one_permission(): void
    {
        foreach (Permissions::ROLES as $role) {
            $this->assertNotSame([], Permissions::forRole($role), "Role [{$role}] holds no permission.");
        }
    }

    #[Test]
    public function unknown_or_missing_role_has_no_permissions(): void
    {
        $this->assertSame([], Permissions::forRole(null));
        $this->assertSame([], Permissions::forRole('rol_inexistente'));
    }

    #[Test]
    public function reception_cannot_complete_consultations(): void
    {
        $this->assertFalse(Permissions::roleHas('recepcionista', Permissions::COMPLETE_APPOINTMENT));
    }

    #[Test]
    public function clinical_roles_and_admin_can_complete_consultations(): void
    {
        foreach (['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente'] as $role) {
            $this->assertTrue(
                Permissions::roleHas($role, Permissions::COMPLETE_APPOINTMENT),
                "Role [{$role}] should keep the consultation close.",
            );
        }
    }

    #[Test]
    public function reception_keeps_the_non_clinical_appointment_flow(): void
    {
        foreach ([
            Permissions::CHECK_IN_APPOINTMENT,
            Permissions::UPDATE_APPOINTMENT_STATUS,
            Permissions::MANAGE_APPOINTMENT,
            Permissions::VIEW_PATIENT,
        ] as $permission) {
            $this->assertTrue(
                Permissions::roleHas('recepcionista', $permission),
                "Reception should keep [{$permission}].",
            );
        }
    }

    #[Test]
    public function the_encounter_doors_are_clinical_only(): void
    {
        foreach ([Permissions::CREATE_ENCOUNTER, Permissions::VOID_ENCOUNTER] as $permission) {
            $this->assertFalse(
                Permissions::roleHas('recepcionista', $permission),
                "Reception holds [{$permission}], but opening or voiding a clinical act is not reception's work.",
            );

            foreach (['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente'] as $role) {
                $this->assertTrue(
                    Permissions::roleHas($role, $permission),
                    "Role [{$role}] should hold [{$permission}].",
                );
            }
        }
    }

    #[Test]
    public function the_encounter_read_is_clinical_only(): void
    {
        foreach (['recepcionista', 'finanzas', 'tecnico_dental'] as $role) {
            $this->assertFalse(
                Permissions::roleHas($role, Permissions::VIEW_ENCOUNTER),
                "Role [{$role}] holds [view.encounter], but the act carries the clinical record.",
            );
        }

        foreach (['administrador', 'odontologo', 'implantologo', 'asistente'] as $role) {
            $this->assertTrue(
                Permissions::roleHas($role, Permissions::VIEW_ENCOUNTER),
                "Role [{$role}] should hold [view.encounter].",
            );
        }
    }

    /**
     * #285 (S3/S5) — the prosthetic case replaced the clinical record on the
     * laboratory desk. Reading the history, its evolutions, its attachments
     * or the act that carries them is not the laboratory's work.
     */
    #[Test]
    public function the_laboratory_desk_does_not_read_the_clinical_record(): void
    {
        foreach ([
            Permissions::MANAGE_MEDICAL_RECORD,
            Permissions::MANAGE_EVOLUTION,
            Permissions::MANAGE_ATTACHMENT,
            Permissions::VIEW_MEDICAL_RECORD_STATS,
            Permissions::VIEW_ENCOUNTER,
        ] as $permission) {
            $this->assertFalse(
                Permissions::roleHas('tecnico_dental', $permission),
                "The laboratory holds [{$permission}], but #285 closes the clinical record on that desk.",
            );

            foreach (['administrador', 'odontologo', 'implantologo', 'asistente'] as $role) {
                $this->assertTrue(
                    Permissions::roleHas($role, $permission),
                    "Role [{$role}] should keep [{$permission}].",
                );
            }
        }
    }

    /**
     * #285 closes the read of the record, not the clinical workflow the
     * laboratory already had (F1/F2). A regression that drops those doors too
     * fails here instead of passing silently.
     */
    #[Test]
    public function the_laboratory_keeps_its_clinical_workflow_doors(): void
    {
        foreach ([
            Permissions::START_CONSULTATION,
            Permissions::COMPLETE_APPOINTMENT,
            Permissions::CREATE_ENCOUNTER,
            Permissions::CLOSE_ENCOUNTER,
            Permissions::VIEW_MY_DAY,
        ] as $permission) {
            $this->assertTrue(
                Permissions::roleHas('tecnico_dental', $permission),
                "The laboratory should keep [{$permission}]: #285 closes the record read, not the workflow.",
            );
        }
    }

    #[Test]
    public function frontend_only_permissions_exist_in_the_catalog(): void
    {
        foreach (Permissions::FRONTEND_ONLY as $permission) {
            $this->assertTrue(Permissions::exists($permission), "Frontend permission [{$permission}] is missing.");
        }
    }
}
