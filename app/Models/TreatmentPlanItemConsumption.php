<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F4 (#284) — the bridge between an act and the plan item it executed.
 *
 * An item is `completed` because an act consumed it, never because somebody
 * edited a status field. The row keeps the act, the quantity and the author,
 * so the plan can show executed, in progress and pending without guessing.
 */
class TreatmentPlanItemConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinical_encounter_id',
        'treatment_plan_item_id',
        'quantity',
        'consumed_by',
        'consumed_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'consumed_at' => 'datetime',
    ];

    public function clinicalEncounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }
}
