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
