<?php

namespace App\Services;

use App\Authorization\Permissions;
use App\Models\AiAnalysisCitation;
use App\Models\AiImageAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * #296 — the only door through which an AI analysis enters a clinical document.
 *
 * An analysis is decision support, so it becomes part of the record only when a
 * professional reviewed it. A citation is refused when the analysis is missing,
 * belongs to another patient, never finished, was never reviewed or was
 * annulled; and an analysis already cited cannot be annulled, because the
 * document that cites it is clinical evidence and would keep a dangling
 * reference.
 */
class AiAnalysisCitationService
{
    /**
     * Refuse the whole request before anything is written, so a refused
     * citation never leaves a half-written evolution or plan behind.
     *
     * @param array<int, int|string> $analysisIds
     */
    public function assertCitable(array $analysisIds, int $patientId, User $user): void
    {
        $ids = $this->normalize($analysisIds);

        if ($ids === []) {
            return;
        }

        // Citing is incorporating: only a role that may read the AI module can
        // put an analysis into the record (spec S2).
        if (! Permissions::roleHas($user->role, Permissions::VIEW_AI_ANALYSIS)) {
            $this->refuse('No tiene permiso para citar análisis de IA en el registro clínico.');
        }

        $analyses = AiImageAnalysis::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            /** @var AiImageAnalysis|null $analysis */
            $analysis = $analyses->get($id);

            $this->assertOneCitable($analysis, $patientId);
        }
    }

    /**
     * Replace the citations of a document with the given analyses.
     *
     * @param array<int, int|string> $analysisIds
     */
    public function sync(Model $document, array $analysisIds, User $user): void
    {
        $ids = $this->normalize($analysisIds);

        $document->citations()->whereNotIn('ai_image_analysis_id', $ids)->delete();

        foreach ($ids as $id) {
            AiAnalysisCitation::query()->firstOrCreate(
                [
                    'citable_type' => $document->getMorphClass(),
                    'citable_id' => $document->getKey(),
                    'ai_image_analysis_id' => $id,
                ],
                ['cited_by' => $user->id],
            );
        }

        $document->unsetRelation('citedAnalyses');
        $document->unsetRelation('citations');
    }

    /**
     * A cited analysis is already part of a clinical document: annulling it
     * would leave that document citing evidence that no longer counts.
     */
    public function assertNotCited(AiImageAnalysis $analysis): void
    {
        if ($analysis->citations()->exists()) {
            throw ValidationException::withMessages([
                'analysis' => ['No se puede anular un análisis citado en una evolución o en un plan.'],
            ]);
        }
    }

    /**
     * @param  array<int, int|string> $analysisIds
     * @return list<int>
     */
    private function normalize(array $analysisIds): array
    {
        $ids = array_map('intval', $analysisIds);

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    private function assertOneCitable(?AiImageAnalysis $analysis, int $patientId): void
    {
        if ($analysis === null) {
            $this->refuse('El análisis citado no existe.');
        }

        if ((int) $analysis->patient_id !== $patientId) {
            $this->refuse('El análisis citado no pertenece al paciente de la historia clínica.');
        }

        if ($analysis->status !== 'completed') {
            $this->refuse('El análisis citado no tiene un resultado completado.');
        }

        if (! $analysis->reviewed) {
            $this->refuse('El análisis no puede citarse porque no fue revisado por un profesional.');
        }

        if ($analysis->isVoided()) {
            $this->refuse('El análisis citado fue anulado y no puede volver a citarse.');
        }
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages([
            'cited_analysis_ids' => [$message],
        ]);
    }
}
