<?php

namespace Tests\Feature\Api;

use App\Models\DiagnosisCatalog;
use App\Models\User;
use Database\Seeders\DiagnosisCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6 (#286) — the CIE-10 catalogue the close codes a diagnosis against.
 *
 * `diagnosis_catalog` existed in the schema since the first migration without a
 * model, a seeder or a route. This surface makes it readable by the clinical
 * roles: the picker of the closure searches it by code or by name, and only an
 * active entry can be coded.
 */
class DiagnosisCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function receptionist(): User
    {
        return User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
    }

    public function test_the_seeder_loads_the_odontological_chapter_and_is_idempotent(): void
    {
        $this->seed(DiagnosisCatalogSeeder::class);
        $first = DiagnosisCatalog::count();

        $this->seed(DiagnosisCatalogSeeder::class);

        $this->assertSame($first, DiagnosisCatalog::count());
        $this->assertSame(20, $first);

        $caries = DiagnosisCatalog::where('cie_code', 'K02.1')->sole();
        $this->assertSame('Caries de la dentina', $caries->name);
        $this->assertTrue($caries->is_active);
        $this->assertNotNull($caries->category);
        $this->assertNotNull($caries->specialty);
    }

    public function test_the_index_searches_by_code_and_by_name(): void
    {
        $this->seed(DiagnosisCatalogSeeder::class);
        $user = $this->clinician();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/diagnoses?q=K02')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.cie_code', 'K02.0');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/diagnoses?q=pulpitis')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cie_code', 'K04.0')
            ->assertJsonPath('data.0.name', 'Pulpitis');
    }

    public function test_the_index_hides_retired_entries_unless_they_are_asked_for(): void
    {
        DiagnosisCatalog::factory()->create(['cie_code' => 'K02.1', 'name' => 'Caries de la dentina']);
        DiagnosisCatalog::factory()->inactive()->create(['cie_code' => 'K99.9', 'name' => 'Entrada retirada']);

        $user = $this->clinician();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/diagnoses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cie_code', 'K02.1');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/diagnoses?is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cie_code', 'K99.9');
    }

    public function test_the_index_paginates(): void
    {
        $this->seed(DiagnosisCatalogSeeder::class);

        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson('/api/diagnoses?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 4);
    }

    public function test_the_detail_publishes_one_entry(): void
    {
        $entry = DiagnosisCatalog::factory()->create([
            'cie_code' => 'K04.7',
            'name' => 'Absceso periapical sin fístula',
            'treatment_guidelines' => 'Drenaje y antibioticoterapia según criterio.',
        ]);

        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson("/api/diagnoses/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('data.cie_code', 'K04.7')
            ->assertJsonPath('data.name', 'Absceso periapical sin fístula')
            ->assertJsonPath('data.treatment_guidelines', 'Drenaje y antibioticoterapia según criterio.');
    }

    public function test_an_unknown_entry_is_404(): void
    {
        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson('/api/diagnoses/999999')
            ->assertNotFound();
    }

    public function test_reception_cannot_read_the_catalogue(): void
    {
        $entry = DiagnosisCatalog::factory()->create();

        $this->actingAs($this->receptionist(), 'sanctum')
            ->getJson('/api/diagnoses')
            ->assertForbidden();

        $this->actingAs($this->receptionist(), 'sanctum')
            ->getJson("/api/diagnoses/{$entry->id}")
            ->assertForbidden();
    }
}
