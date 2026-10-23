<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #288 — option A/B for a plan line.
 *
 * The rejected option is a first-class row: it keeps its date and its reason,
 * so "the patient declined the implant" survives the edit that accepted the
 * bridge.
 */
class TreatmentPlanItemAlternative extends Model
{
    use HasFactory;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'treatment_plan_item_id',
        'option_code',
        'procedure_catalog_id',
        'procedure_name',
        'procedure_description',
        'dental_piece_id',
        'tooth_surface_id',
        'site_code',
        'quantity',
        'unit_cost',
        'total_cost',
        'status',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    public function procedureCatalog(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_catalog_id');
    }

    public function dentalPiece(): BelongsTo
    {
        return $this->belongsTo(DentalPiece::class);
    }

    public function toothSurface(): BelongsTo
    {
        return $this->belongsTo(ToothSurface::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
