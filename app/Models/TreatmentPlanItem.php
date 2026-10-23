<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_plan_id',
        'procedure_id',
        'procedure_catalog_id',
        'dental_piece_id',
        'tooth_surface_id',
        'site_code',
        'procedure_name',
        'procedure_description',
        'specialty',
        'quantity',
        'unit_cost',
        'total_cost',
        'estimated_duration_minutes',
        'phase_number',
        'sequence',
        'priority',
        'rejected_at',
        'rejection_reason',
        'status',
        'notes',
        'materials_required',
        'requires_anesthesia',
        'is_optional',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'quantity' => 'integer',
        'sequence' => 'integer',
        'rejected_at' => 'datetime',
        'requires_anesthesia' => 'boolean',
        'is_optional' => 'boolean',
        'materials_required' => 'array',
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

    public function toothSurface(): BelongsTo
    {
        return $this->belongsTo(ToothSurface::class);
    }

    public function procedureCatalog(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_catalog_id');
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

    public function procedureMaterials(): HasMany
    {
        return $this->hasMany(ProcedureMaterial::class, 'treatment_plan_item_id');
    }

    /**
     * F4 (#284): the acts that executed this line. Status is derived from these
     * rows, never written directly by the plan API.
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(TreatmentPlanItemConsumption::class);
    }

    /**
     * #288: option A/B for this line and the record of the one that was
     * declined.
     */
    public function alternatives(): HasMany
    {
        return $this->hasMany(TreatmentPlanItemAlternative::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('phase_number')->orderBy('sequence')->orderBy('id');
    }

    public function executedQuantity(): float
    {
        return round((float) $this->consumptions()->sum('quantity'), 2);
    }

    public function pendingQuantity(): float
    {
        return round(max(0, (float) $this->quantity - $this->executedQuantity()), 2);
    }

    /**
     * Devuelve los materiales requeridos declarados en el JSON del item.
     *
     * @return array<int, string>
     */
    public function requiredMaterialsList(): array
    {
        $raw = $this->materials_required;

        if (is_array($raw)) {
            return array_values(array_filter($raw, fn ($v) => is_string($v) && $v !== ''));
        }

        if (is_string($raw) && $raw !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/[,;\n]/', $raw))));
        }

        return [];
    }
}
