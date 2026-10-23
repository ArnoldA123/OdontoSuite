<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * #288 — an immutable picture of a plan at one moment.
 *
 * The snapshot holds the definition (header plus lines), never the execution:
 * the acts that consumed a line live on the plan item. A version may move from
 * draft to accepted once; its content can never be rewritten.
 */
class TreatmentPlanVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'treatment_plan_id',
        'version_number',
        'status',
        'snapshot',
        'reason',
        'created_by',
        'accepted_at',
        'accepted_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->isDirty(['treatment_plan_id', 'version_number', 'snapshot'])
            && $version->getOriginal('status') !== self::STATUS_DRAFT) {
                throw new RuntimeException('La versión del plan es inmutable: su contenido no se reescribe.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('La versión del plan es inmutable: no se elimina.');
        });
    }
}
