<?php

namespace App\Models;

use App\Support\ClinicalAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiImageAnalysis extends Model
{
    use HasFactory;

    /**
     * #296 — the wording the professional must be able to declare to the
     * patient. One source: the API publishes it and the interface prints it.
     */
    public const ASSISTIVE_NOTICE = 'Resultado de apoyo a la decisión asistido por inteligencia artificial. '
        .'No es un diagnóstico y no sustituye la evaluación ni el criterio del profesional.';

    protected $fillable = [
        'clinical_attachment_id',
        'clinical_encounter_id',
        'patient_id',
        'requested_by',
        'status',
        'prompt_sent',
        'findings',
        'recommendations',
        'confidence_score',
        'raw_response',
        'model_used',
        'tokens_used',
        'estimated_cost',
        'voided_at',
        'voided_by',
        'void_reason',
        'notice_acknowledged_at',
        'notice_acknowledged_by',
        'reviewed',
        'reviewed_by',
        'reviewed_at',
        'review_decision',
        'review_notes',
    ];

    /**
     * Provider artifacts kept for audit only: the exact prompt sent to the
     * model and its full raw completion never leave the API surface.
     */
    protected $hidden = [
        'prompt_sent',
        'raw_response',
    ];

    protected $casts = [
        'findings' => 'array',
        'recommendations' => 'array',
        'confidence_score' => 'decimal:2',
        'reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'tokens_used' => 'integer',
        'estimated_cost' => 'decimal:4',
        'voided_at' => 'datetime',
        'notice_acknowledged_at' => 'datetime',
    ];

    /**
     * Scope the rows to what the viewer may read (issue #214): everything for an
     * administrator, and otherwise the analyses she requested or reviewed plus
     * the ones of the patients she reaches clinically.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'administrador') {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped->where('requested_by', $user->id)
                ->orWhere('reviewed_by', $user->id)
                ->orWhereIn('patient_id', ClinicalAccess::patientIdsFor($user));
        });
    }

    // Relaciones
    public function clinicalAttachment(): BelongsTo
    {
        return $this->belongsTo(ClinicalAttachment::class);
    }

    /**
     * #296: the clinical act the analysis supports, inherited from its
     * attachment (F2, #282).
     */
    public function clinicalEncounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    /**
     * #296: the documents that cited this analysis. Citations exist only for a
     * reviewed analysis, so the relation is the proof of incorporation.
     */
    public function citations(): HasMany
    {
        return $this->hasMany(AiAnalysisCitation::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function noticeAcknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notice_acknowledged_by');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * #296 — the payload of a cited analysis.
     *
     * A citation travels inside a clinical document whose reader may not hold
     * `view.ai_analysis` (the clinical matrix of #283 still lets the laboratory
     * read the record), so the relation carries the decision, not the provider
     * artifacts: no findings, tokens, cost or review notes.
     */
    public function scopeCitationSummary(Builder $query): Builder
    {
        return $query->select([
            'ai_image_analyses.id',
            'ai_image_analyses.review_decision',
            'ai_image_analyses.reviewed_at',
            'ai_image_analyses.confidence_score',
        ]);
    }

    /**
     * #296: annulled analyses leave the working set but keep their row.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->whereNotNull('voided_at');
    }

    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByAttachment($query, $attachmentId)
    {
        return $query->where('clinical_attachment_id', $attachmentId);
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'completed')
            ->where('reviewed', false);
    }

    public function scopeReviewed($query)
    {
        return $query->where('reviewed', true);
    }

    public function scopeByReviewDecision($query, $decision)
    {
        return $query->where('review_decision', $decision);
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'completed' => 'Completado',
            'failed' => 'Fallido',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getReviewDecisionLabelAttribute()
    {
        $labels = [
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'partial' => 'Parcial',
        ];

        return $labels[$this->review_decision] ?? 'Sin revisar';
    }

    public function getConfidenceLevelAttribute()
    {
        if ($this->confidence_score >= 90) {
            return 'high';
        }
        if ($this->confidence_score >= 70) {
            return 'medium';
        }

        return 'low';
    }

    public function getConfidenceColorAttribute()
    {
        $level = $this->confidence_level;
        $colors = [
            'high' => 'green',
            'medium' => 'yellow',
            'low' => 'red',
        ];

        return $colors[$level] ?? 'gray';
    }

    // Métodos
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPendingReview(): bool
    {
        return $this->isCompleted() && ! $this->reviewed;
    }

    public function isReviewed(): bool
    {
        return $this->reviewed;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isAssistive(): bool
    {
        return true;
    }

    public function markAsReviewed(User $user, string $decision, ?string $notes = null): void
    {
        $this->update([
            'reviewed' => true,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_decision' => $decision,
            'review_notes' => $notes,
        ]);
    }

    public function getMainFindings(): array
    {
        if (! $this->findings) {
            return [];
        }

        return array_slice($this->findings, 0, 3); // Primeros 3 hallazgos
    }

    public function getTopRecommendations(): array
    {
        if (! $this->recommendations) {
            return [];
        }

        return array_slice($this->recommendations, 0, 3); // Primeras 3 recomendaciones
    }
}
