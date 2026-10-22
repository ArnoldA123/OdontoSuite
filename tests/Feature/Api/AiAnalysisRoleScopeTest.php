<?php

namespace Tests\Feature\Api;

use App\Authorization\Permissions;
use App\Models\AiImageAnalysis;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AiImageAnalysisService;
use App\Services\ClinicalAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * #296 (spec S2) — the laboratory desktop does not reach the AI module.
 *
 * `tecnico_dental` keeps the clinical record and the treatment plan, but an AI
 * analysis is decision support over a diagnosis the laboratory does not own:
 * the four AI capabilities belong to the three roles that diagnose. The scope
 * is enforced twice on purpose — the catalog (the middleware verdict on every
 * route) and `AiImageAnalysisPolicy::viewAny()` (the ability the controller
 * re-checks) — and the SPA must not offer a door the API closes.
 */
class AiAnalysisRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const AI_ROLES = ['administrador', 'odontologo', 'implantologo'];

    private function userOf(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function relatedPatient(User $professional): Patient
    {
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'user_id' => $professional->id,
            'patient_id' => $patient->id,
        ]);

        return $patient;
    }

    private function analysisFor(Patient $patient, User $requester): AiImageAnalysis
    {
        return AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $requester->id,
            'status' => 'completed',
            'reviewed' => false,
        ]);
    }

    public function test_every_ai_capability_grants_exactly_the_three_specialist_roles(): void
    {
        foreach ([
            Permissions::VIEW_AI_ANALYSIS,
            Permissions::CREATE_AI_ANALYSIS,
            Permissions::REVIEW_AI_ANALYSIS,
            Permissions::VOID_AI_ANALYSIS,
        ] as $permission) {
            $this->assertSame(
                self::AI_ROLES,
                Permissions::rolesFor($permission),
                "Permission [{$permission}] must grant exactly the specialist roles.",
            );

            $this->assertFalse(
                Permissions::roleHas('tecnico_dental', $permission),
                "Role [tecnico_dental] must not hold [{$permission}].",
            );
        }
    }

    public function test_tecnico_dental_is_forbidden_on_every_ai_surface(): void
    {
        $technician = $this->userOf('tecnico_dental');
        // The technician does reach this patient clinically; the denial must
        // come from the AI capability and not from a missing relationship.
        $patient = $this->relatedPatient($technician);
        $analysis = $this->analysisFor($patient, $this->userOf('odontologo'));

        // S2 is a middleware verdict: the controller is never reached, so the
        // services are replaced to keep this test away from the model provider.
        $this->mock(AiImageAnalysisService::class);
        $this->mock(ClinicalAttachmentService::class);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->actingAs($technician)->getJson('/api/ai-analysis')->assertForbidden();
        $this->actingAs($technician)->getJson('/api/ai-analysis/stats')->assertForbidden();
        $this->actingAs($technician)->getJson('/api/ai-analysis/pending')->assertForbidden();
        $this->actingAs($technician)->getJson('/api/ai-analysis/'.$analysis->id)->assertForbidden();
        $this->actingAs($technician)->getJson('/api/ai-analysis/patient/'.$patient->id)->assertForbidden();
        $this->actingAs($technician)->getJson('/api/ai-analysis/attachment/'.$analysis->clinical_attachment_id)->assertForbidden();
        $this->actingAs($technician)->postJson('/api/ai-analysis/analyze/'.$analysis->clinical_attachment_id)->assertForbidden();
        $this->actingAs($technician)->postJson('/api/ai-analysis/'.$analysis->id.'/review', ['decision' => 'accepted'])->assertForbidden();
        $this->actingAs($technician)->post('/api/ai-analysis/upload-and-analyze', [
            'patient_id' => $patient->id,
            'category' => 'radiografia',
            'image' => UploadedFile::fake()->createWithContent('xray.png', $png),
        ])->assertForbidden();
        $this->actingAs($technician)->postJson('/api/ai-analysis/'.$analysis->id.'/void', ['reason' => 'Duplicado'])->assertForbidden();

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'reviewed' => false,
            'voided_at' => null,
        ]);
        $this->assertDatabaseCount('ai_image_analyses', 1);
        // Nothing was uploaded on the way to the denial.
        $this->assertDatabaseCount('clinical_attachments', 1);
    }

    public function test_an_implantologo_with_a_clinical_relationship_still_reads_the_list(): void
    {
        $implantologist = $this->userOf('implantologo');
        $patient = $this->relatedPatient($implantologist);
        $analysis = $this->analysisFor($patient, $this->userOf('odontologo'));

        $this->actingAs($implantologist)
            ->getJson('/api/ai-analysis')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $analysis->id);
    }

    public function test_the_spa_navigation_and_permission_map_follow_the_catalog(): void
    {
        $layout = $this->source('resources/js/components/layout/AppLayout.vue');

        $this->assertSame(
            1,
            preg_match(
                "/to: '\\/ai-analysis',.*?roles: \\[([^\\]]*)\\]/s",
                $layout,
                $entry,
            ),
            'AppLayout.vue must declare the /ai-analysis navigation entry.',
        );

        preg_match_all("/'([a-z_]+)'/", $entry[1], $roles);

        $this->assertSame(self::AI_ROLES, $roles[1], 'The /ai-analysis entry must name exactly the specialist roles.');
        $this->assertNotContains('tecnico_dental', $roles[1], 'The laboratory desktop must not see the AI entry.');

        $map = $this->source('resources/js/composables/usePermissions.js');

        $this->assertMatchesRegularExpression(
            "/'ai-analysis\\.void':\\s*'void\\.ai_analysis'/",
            $map,
            "usePermissions.js must map 'ai-analysis.void' to '".Permissions::VOID_AI_ANALYSIS."'.",
        );
    }

    private function source(string $relative): string
    {
        $path = base_path($relative);
        $source = file_get_contents($path);

        $this->assertNotFalse($source, "{$relative} must exist");

        return $source;
    }
}
