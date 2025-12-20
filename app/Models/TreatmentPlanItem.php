<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_plan_id',
        'procedure_id',
        'dental_piece_id',
        'procedure_name',
        'procedure_description',
        'specialty',
        'quantity',
        'unit_cost',
        'total_cost',
        'estimated_duration_minutes',
        'phase_number',
        'status',
        'notes',
        'materials_required',
        'requires_anesthesia',
        'is_optional'
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'requires_anesthesia' => 'boolean',
        'is_optional' => 'boolean'
    ];

    // Relaciones
    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function dentalPiece(): BelongsTo
    {
        return $this->belongsTo(DentalPiece::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPhase($query, $phase)
    {
        return $query->where('phase_number', $phase);
    }
}
