<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Desks;
use App\Authorization\Permissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #283 (F3) S1 — «matriz escritorio x permiso para los 6 escritorios».
 *
 * The six desks are the user groups F0 (#280, S3) fixed: recepción, clínico,
 * asistente, laboratorio, finanzas y administración. This test keeps the matrix
 * honest without a database: every role sits at exactly one desk, every
 * permission of the catalog belongs to at least one desk, and no desk invents a
 * permission that the catalog does not grant.
 */
class DeskMatrixTest extends TestCase
{
    #[Test]
    public function there_are_exactly_six_desks(): void
    {
        $this->assertCount(6, Desks::all());
        $this->assertSame([
            Desks::RECEPTION,
            Desks::CLINICIAN,
            Desks::ASSISTANT,
            Desks::LABORATORY,
            Desks::FINANCE,
            Desks::ADMINISTRATION,
        ], Desks::all());
    }

    #[Test]
    public function every_role_sits_at_exactly_one_desk(): void
    {
        $seen = [];

        foreach (Desks::all() as $desk) {
            foreach (Desks::roles($desk) as $role) {
                $this->assertArrayNotHasKey(
                    $role,
                    $seen,
                    "Role [{$role}] sits at two desks [".($seen[$role] ?? 'none')."] and [{$desk}].",
                );
                $this->assertContains($role, Permissions::ROLES);
                $seen[$role] = $desk;
            }
        }

        foreach (Permissions::ROLES as $role) {
            $this->assertArrayHasKey($role, $seen, "Role [{$role}] does not sit at any desk.");
        }
    }

    #[Test]
    public function every_catalog_permission_belongs_to_at_least_one_desk(): void
    {
        $covered = [];

        foreach (Desks::all() as $desk) {
            foreach (Desks::permissions($desk) as $permission) {
                $covered[$permission] = true;
            }
        }

        foreach (Permissions::all() as $permission) {
            $this->assertArrayHasKey(
                $permission,
                $covered,
                "Permission [{$permission}] belongs to no desk.",
            );
        }
    }

    #[Test]
    public function no_desk_claims_a_permission_outside_the_catalog(): void
    {
        foreach (Desks::all() as $desk) {
            foreach (Desks::permissions($desk) as $permission) {
                $this->assertTrue(
                    Permissions::exists($permission),
                    "Desk [{$desk}] claims unknown permission [{$permission}].",
                );
            }
        }
    }

    #[Test]
    public function the_desks_cover_the_catalog_exactly_once(): void
    {
        $this->assertSame([], Desks::uncoveredPermissions());
        $this->assertCount(count(Permissions::all()), Desks::permissionMatrix());
    }

    #[Test]
    public function reception_does_not_reach_the_clinical_close(): void
    {
        $reception = Desks::permissions(Desks::RECEPTION);

        $this->assertContains(Permissions::CHECK_IN_APPOINTMENT, $reception);
        $this->assertNotContains(Permissions::COMPLETE_APPOINTMENT, $reception);
        $this->assertNotContains(Permissions::MANAGE_EVOLUTION, $reception);
        $this->assertNotContains(Permissions::START_CONSULTATION, $reception);
    }

    #[Test]
    public function the_laboratory_desk_does_not_reach_ai_analysis(): void
    {
        $laboratory = Desks::permissions(Desks::LABORATORY);

        $this->assertNotContains(Permissions::VIEW_AI_ANALYSIS, $laboratory);
    }

    /**
     * #313 (decision D1) — the specialty record carries the surgical narrative
     * the laboratory does not need. Reading and writing are the same permission,
     * so the desk leaves the module whole while keeping its prosthetic case and
     * its treatment plans.
     */
    #[Test]
    public function the_laboratory_desk_does_not_reach_the_specialty_record(): void
    {
        $this->assertNotContains(
            Permissions::MANAGE_SPECIALTY_RECORD,
            Desks::permissions(Desks::LABORATORY),
        );
        $this->assertContains(
            Permissions::MANAGE_SPECIALTY_RECORD,
            Desks::permissions(Desks::CLINICIAN),
        );
    }

    /**
     * Product decision: booking is counter work. The permission reaches the
     * reception and administration desks and no other, while the clinical desks
     * keep the agenda they operate.
     */
    #[Test]
    public function the_booking_door_belongs_to_reception_and_administration(): void
    {
        foreach ([Desks::RECEPTION, Desks::ADMINISTRATION] as $desk) {
            $this->assertContains(
                Permissions::CREATE_APPOINTMENT,
                Desks::permissions($desk),
                "Desk [{$desk}] answers for the counter and should book appointments.",
            );
        }

        foreach ([Desks::CLINICIAN, Desks::ASSISTANT, Desks::LABORATORY, Desks::FINANCE] as $desk) {
            $this->assertNotContains(
                Permissions::CREATE_APPOINTMENT,
                Desks::permissions($desk),
                "Desk [{$desk}] holds [create.appointment], but it does not book for patients.",
            );
        }

        foreach ([Desks::CLINICIAN, Desks::ASSISTANT, Desks::LABORATORY] as $desk) {
            $this->assertContains(
                Permissions::MANAGE_APPOINTMENT,
                Desks::permissions($desk),
                "Desk [{$desk}] should keep the agenda it reads and operates.",
            );
        }
    }

    #[Test]
    public function a_role_at_more_than_one_desk_is_reported_by_the_matrix(): void
    {
        $this->assertSame([Desks::CLINICIAN], Desks::desksForRole('odontologo'));
        $this->assertSame([Desks::ADMINISTRATION], Desks::desksForRole('administrador'));
    }
}
