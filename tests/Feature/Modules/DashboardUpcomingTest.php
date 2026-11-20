<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * U2 — the dashboard stopped publishing appointment lists.
 *
 * This file used to pin GET /api/dashboard/upcoming (window, ordering, limit,
 * resource shape and branch scoping). The week preview and the today list left
 * with their page blocks, so the contract is now their absence: both routes
 * answer 404 for the widest role, and the two retained dashboard reads (the
 * summary and the commercial pending answers) keep answering.
 *
 * Runs against the MySQL harness because RefreshDatabase rebuilds every table
 * (AGENTS.md §6).
 */
class DashboardUpcomingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    public function test_the_retired_appointment_lists_answer_404(): void
    {
        $admin = $this->admin();

        foreach ([
            '/api/dashboard/upcoming',
            '/api/dashboard/appointments-today',
            '/api/dashboard/today',
        ] as $retired) {
            $this->actingAs($admin, 'sanctum')
                ->getJson($retired)
                ->assertNotFound();
        }
    }

    public function test_the_retained_dashboard_reads_keep_answering(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => ['appointments_today'], 'meta'])
            ->assertJsonPath('data.cash_session.status', 'closed');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/pending')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_the_retired_lists_are_gone_for_every_authenticated_desk(): void
    {
        foreach (['recepcionista', 'odontologo', 'tecnico_dental', 'finanzas'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/dashboard/upcoming')
                ->assertNotFound();

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/dashboard/appointments-today')
                ->assertNotFound();
        }
    }
}
