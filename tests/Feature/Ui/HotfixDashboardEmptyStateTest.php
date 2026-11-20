<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-007 + U2 — the appointments empty state is retired with its
 * block.
 *
 * The dashboard stopped listing appointments, so the today-appointments empty
 * state (data-state="empty-appointments", inline line-art SVG, informational
 * copy) has no page to live on. The contract this file pins now is the
 * retirement plus its replacement: the page carries no empty state, no
 * appointment rows and no appointment CTA, and the way in is the Agenda
 * destination card.
 */
class HotfixDashboardEmptyStateTest extends TestCase
{
    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3).self::DASHBOARD_PAGE_REL;
    }

    private function dashboardSource(): string
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (U2 source boundary)'
        );

        return (string) file_get_contents(self::dashboardPagePath());
    }

    public function test_the_appointments_empty_state_is_retired(): void
    {
        $source = $this->dashboardSource();

        $this->assertStringNotContainsString(
            'data-state="empty-appointments"',
            $source,
            'DashboardPage.vue must not keep the retired appointments empty-state hook (U2).'
        );
        $this->assertStringNotContainsString(
            '<DashboardSectionEmpty',
            $source,
            'DashboardPage.vue must not render the shared section-empty wrapper anymore: its lists are gone (U2).'
        );
        $this->assertStringNotContainsString(
            'Sin citas para hoy',
            $source,
            'DashboardPage.vue must not keep the retired empty-state title.'
        );
        $this->assertStringContainsString(
            'data-dashboard-block="destinations"',
            $source,
            'The replacement contract is the destinations block.'
        );
        $this->assertStringContainsString(
            "to: '/calendar'",
            $source,
            'The agenda is the destination that now owns the day list.'
        );
    }

    public function test_the_retired_empty_state_copy_and_rows_are_gone(): void
    {
        $source = $this->dashboardSource();

        foreach ([
            'Crear nueva cita',
            'sección de calendario',
            'Cuando registres citas',
            'data-appointment-row',
            'data-state="error-appointments"',
        ] as $retired) {
            $this->assertStringNotContainsString(
                $retired,
                $source,
                "DashboardPage.vue must not keep \"{$retired}\" once the agenda block is retired (U2)."
            );
        }
    }
}
