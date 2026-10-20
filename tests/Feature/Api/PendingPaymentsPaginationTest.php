<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Issue #198 — GET /api/pending-payments returned every completed appointment
 * with a balance and a meta with only `total` and `filters`, so the Cobros tab
 * had to page the whole payload on the client. The endpoint now paginates with
 * `per_page` and answers the full server-side meta the UI already understands.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class PendingPaymentsPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function completedAppointment(Carbon $scheduledAt, ?Patient $patient = null): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => ($patient ?? Patient::factory()->create())->id,
            'status' => 'completed',
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 30,
        ]);
    }

    /** @return array<string, mixed> */
    private function fetch(string $query): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/cash-register/pending-payments'.$query)
            ->assertOk()
            ->json();
    }

    public function test_the_endpoint_pages_the_collection_and_returns_the_full_meta(): void
    {
        foreach (range(1, 5) as $day) {
            $this->completedAppointment(now()->subDays($day)->setTime(9, 0));
        }

        $payload = $this->fetch('?per_page=2&page=2');

        $this->assertCount(2, $payload['data']);
        $this->assertSame(2, $payload['meta']['current_page']);
        $this->assertSame(3, $payload['meta']['last_page']);
        $this->assertSame(2, $payload['meta']['per_page']);
        $this->assertSame(5, $payload['meta']['total']);
    }

    public function test_the_default_page_is_bounded_instead_of_returning_the_whole_clinic(): void
    {
        foreach (range(1, 20) as $day) {
            $this->completedAppointment(now()->subDays($day)->setTime(9, 0));
        }

        $payload = $this->fetch('');

        $this->assertCount(15, $payload['data']);
        $this->assertSame(15, $payload['meta']['per_page']);
        $this->assertSame(20, $payload['meta']['total']);
        $this->assertSame(1, $payload['meta']['current_page']);
        $this->assertSame(2, $payload['meta']['last_page']);
    }

    public function test_the_page_size_is_clamped_to_the_supported_range(): void
    {
        $this->completedAppointment(now()->subDay()->setTime(9, 0));

        $this->assertSame(100, $this->fetch('?per_page=500')['meta']['per_page']);
        $this->assertSame(1, $this->fetch('?per_page=0')['meta']['per_page']);
    }

    public function test_the_filters_scope_the_paginated_population_and_stay_in_the_meta(): void
    {
        $ana = Patient::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
        ]);

        $this->completedAppointment(now()->subDay()->setTime(9, 0), $ana);
        $this->completedAppointment(now()->subDays(2)->setTime(9, 0));
        $this->completedAppointment(now()->subDays(3)->setTime(9, 0));

        $payload = $this->fetch('?search=Ana&per_page=1');

        $this->assertCount(1, $payload['data']);
        $this->assertSame(1, $payload['meta']['total']);
        $this->assertSame(1, $payload['meta']['last_page']);
        $this->assertSame('Ana', $payload['meta']['filters']['search']);
    }

    public function test_the_newest_appointment_leads_the_first_page(): void
    {
        $older = $this->completedAppointment(now()->subDays(3)->setTime(9, 0));
        $newer = $this->completedAppointment(now()->subDay()->setTime(9, 0));

        $payload = $this->fetch('?per_page=1&page=1');
        $this->assertSame($newer->id, $payload['data'][0]['id']);

        $payload = $this->fetch('?per_page=1&page=2');
        $this->assertSame($older->id, $payload['data'][0]['id']);
    }
}
