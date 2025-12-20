<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiImageAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinical_attachment_id',
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
        'reviewed',
        'reviewed_by',
        'reviewed_at',
        'review_decision',
        'review_notes'
    ];

    protected $casts = [
        'findings' => 'array',
        'recommendations' => 'array',
        'confidence_score' => 'decimal:2',
        'reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'tokens_used' => 'integer'
    ];

    // Relaciones
    public function clinicalAttachment(): BelongsTo
    {
        return $this->belongsTo(ClinicalAttachment::class);
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
            'failed' => 'Fallido'
        ];
        return $labels[$this->status] ?? $this->status;
    }

    public function getReviewDecisionLabelAttribute()
    {
        $labels = [
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'partial' => 'Parcial'
        ];
        return $labels[$this->review_decision] ?? 'Sin revisar';
    }

    public function getConfidenceLevelAttribute()
    {
        if ($this->confidence_score >= 90) return 'high';
        if ($this->confidence_score >= 70) return 'medium';
        return 'low';
    }

    public function getConfidenceColorAttribute()
    {
        $level = $this->confidence_level;
        $colors = [
            'high' => 'green',
            'medium' => 'yellow',
            'low' => 'red'
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
        return $this->isCompleted() && !$this->reviewed;
    }

    public function isReviewed(): bool
    {
        return $this->reviewed;
    }

    public function markAsReviewed(User $user, string $decision, ?string $notes = null): void
    {
        $this->update([
            'reviewed' => true,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_decision' => $decision,
            'review_notes' => $notes
        ]);
    }

    public function getMainFindings(): array
    {
        if (!$this->findings) return [];

        return array_slice($this->findings, 0, 3); // Primeros 3 hallazgos
    }

    public function getTopRecommendations(): array
    {
        if (!$this->recommendations) return [];

        return array_slice($this->recommendations, 0, 3); // Primeras 3 recomendaciones
    }
}
