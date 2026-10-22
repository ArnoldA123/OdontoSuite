<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * #296 — the citation that makes an AI analysis part of a clinical document.
 *
 * Only a reviewed analysis can be cited, so the row is proof that a
 * professional looked at the finding before it reached the evolution or the
 * plan. Append-only: it carries `created_at` and no `updated_at`, and the
 * analysis row restricts deletion because an annulled analysis keeps its
 * citations as part of the trace.
 */
class AiAnalysisCitation extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'citable_type',
        'citable_id',
        'ai_image_analysis_id',
        'cited_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * The document that cites the analysis: a `ClinicalEvolution` or a
     * `TreatmentPlan`.
     */
    public function citable(): MorphTo
    {
        return $this->morphTo();
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(AiImageAnalysis::class, 'ai_image_analysis_id');
    }

    public function citedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cited_by');
    }
}
