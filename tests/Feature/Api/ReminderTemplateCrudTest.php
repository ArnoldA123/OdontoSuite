<?php

namespace Tests\Feature\Api;

use App\Models\ReminderTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED test (slice 03, T-03.10) for ReminderTemplateController full CRUD (BF-002).
 *
 * Acceptance:
 *  - GET /api/reminder-templates       -> 200 with {data, meta.message}
 *  - POST /api/reminder-templates      -> 201 with {data, meta.message}
 *  - GET /api/reminder-templates/{id}  -> 200 with {data}
 *  - PUT /api/reminder-templates/{id}  -> 200 with {data, meta.message}
 *  - DELETE /api/reminder-templates/{id} -> 204
 *  - Only role:administrador may write; other roles get 403.
 *
 * @see specs/03-stubs-501-implement.md
 */
class ReminderTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    public function test_index_returns_200_envelope(): void
    {
        ReminderTemplate::create([
            'name' => '24h',
            'type' => '24h',
            'subject' => 'Recordatorio 24h',
            'body_html' => '<p>24h reminder</p>',
            'body_text' => '24h reminder',
            'is_active' => true,
        ]);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reminder-templates')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['message']]);
    }

    public function test_store_creates_template_and_returns_201(): void
    {
        $admin = $this->admin();

        $payload = [
            'name' => '48h Template',
            'type' => '48h',
            'subject' => 'Recordatorio 48 horas',
            'body_html' => '<p>Hola {{patient_name}}</p>',
            'body_text' => 'Hola {{patient_name}}',
            'is_active' => true,
        ];

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/reminder-templates', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'type'], 'meta' => ['message']]);

        $this->assertDatabaseHas('reminder_templates', [
            'name' => '48h Template',
            'type' => '48h',
        ]);
    }

    public function test_store_requires_name_and_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/reminder-templates', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_show_returns_200_with_data(): void
    {
        $admin = $this->admin();

        $template = ReminderTemplate::create([
            'name' => '24h',
            'type' => '24h',
            'subject' => 'Recordatorio',
            'body_html' => '<p>Hola</p>',
            'body_text' => 'Hola',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/reminder-templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.name', '24h');
    }

    public function test_update_modifies_template(): void
    {
        $admin = $this->admin();

        $template = ReminderTemplate::create([
            'name' => '24h',
            'type' => '24h',
            'subject' => 'Recordatorio',
            'body_html' => '<p>Hola</p>',
            'body_text' => 'Hola',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/reminder-templates/{$template->id}", [
                'name' => '24h v2',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', '24h v2')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('reminder_templates', [
            'id' => $template->id,
            'name' => '24h v2',
            'is_active' => false,
        ]);
    }

    public function test_destroy_returns_204(): void
    {
        $admin = $this->admin();

        $template = ReminderTemplate::create([
            'name' => '24h',
            'type' => '24h',
            'subject' => 'Recordatorio',
            'body_html' => '<p>Hola</p>',
            'body_text' => 'Hola',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/reminder-templates/{$template->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('reminder_templates', ['id' => $template->id]);
    }

    public function test_non_admin_cannot_create_template(): void
    {
        $user = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reminder-templates', [
                'name' => 'hacker',
                'type' => 'hacker',
                'subject' => 'h',
                'body_html' => 'h',
                'body_text' => 'h',
            ])
            ->assertForbidden();
    }

    public function test_show_returns_404_for_missing_template(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reminder-templates/999999')
            ->assertNotFound();
    }
}
