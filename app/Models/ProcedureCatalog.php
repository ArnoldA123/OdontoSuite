<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureCatalog extends Model
{
    use HasFactory;

    protected $table = 'procedure_catalog';

    protected $fillable = [
        'code',
        'name',
        'description',
        'specialty',
        'default_cost',
        'default_duration_minutes',
        'requirements',
        'materials_needed',
        'requires_anesthesia',
        'requires_radiographs',
        'steps',
        'contraindications',
        'post_procedure_care',
        'is_active',
    ];

    protected $casts = [
        'default_cost' => 'decimal:2',
        'default_duration_minutes' => 'integer',
        'requires_anesthesia' => 'boolean',
        'requires_radiographs' => 'boolean',
        'steps' => 'array',
        'is_active' => 'boolean',
    ];

    public function treatmentPlanItems(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class, 'procedure_catalog_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySpecialty(Builder $query, ?string $specialty): Builder
    {
        if (!$specialty) {
            return $query;
        }
        return $query->where('specialty', $specialty);
    }

    /**
     * Devuelve los materiales sugeridos como array.
     * La columna es TEXT (no JSON), por lo que splitteamos por coma / salto de línea.
     *
     * @return array<int, string>
     */
    public function materialsNeededList(): array
    {
        $raw = $this->materials_needed;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]/', $raw))));
    }

    /**
     * Aplica los defaults del catálogo sobre un array de item ya en construcción.
     * El clínico puede override `unit_cost` después si quiere.
     *
     * @return array<string, mixed>
     */
    public function applyDefaultsToItem(array $item = []): array
    {
        $item['procedure_catalog_id'] = $this->id;
        $item['procedure_name'] = $item['procedure_name'] ?? $this->name;
        $item['specialty'] = $item['specialty'] ?? $this->specialty;
        $item['unit_cost'] = $item['unit_cost'] ?? (float) $this->default_cost;
        $item['estimated_duration_minutes'] = $item['estimated_duration_minutes'] ?? $this->default_duration_minutes;
        $item['requires_anesthesia'] = $item['requires_anesthesia'] ?? $this->requires_anesthesia;

        if (empty($item['materials_required']) && !empty($this->materials_needed)) {
            $item['materials_required'] = $this->materialsNeededList();
        }

        return $item;
    }
}
