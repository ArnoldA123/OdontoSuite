<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureCatalog extends Model
{
    use HasFactory;

    protected $table = 'procedure_catalog';

    protected $fillable = [
        'code',
        'name',
        'description',
        // 'legacy_specialty' (string) es LEGACY. Mantenido en BD por compatibilidad
        // con ProcedureCatalogResource y ProcedureCatalogController (auditoría).
        // Sprint 2 (DM-7): marcado @deprecated. Usar specialty()->code (FK).
        'legacy_specialty',
        'specialty_id',
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

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'specialty_id');
    }

    /**
     * Devuelve el codigo de la especialidad (FK) del procedimiento.
     * Si specialty_id es null, cae al campo legacy como ultimo recurso.
     *
     * Sprint 2 fix (DM-7): accessor para que el resto del código lea de la FK
     * sin tocar la columna legacy_specialty. Una vez que specialty_id este
     * 100% poblado en produccion, se podra hacer drop del campo legacy.
     */
    public function getSpecialtyCodeAttribute(): ?string
    {
        if ($this->specialty_id && $this->specialty) {
            return $this->specialty->code;
        }
        return $this->legacy_specialty;
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_favorite_procedures')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('user_favorite_procedures.position');
    }

    /**
     * Sprint 3 fix (IM-7): historial de versiones del procedimiento.
     */
    public function versions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProcedureCatalogVersion::class, 'procedure_catalog_id')
            ->orderByDesc('changed_at');
    }

    /**
     * Sprint 4 fix (IM-8): traducciones del procedimiento (1 por locale).
     */
    public function translations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProcedureCatalogTranslation::class, 'procedure_catalog_id');
    }

    /**
     * Sprint 4 fix (IM-8): resuelve un campo traducido para un locale dado.
     *
     * Si existe traduccion para $locale y el campo $field no es null en la
     * traduccion, devuelve el valor traducido. Si no, devuelve el valor
     * original del modelo.
     *
     * Ejemplo: $pc->translate('en', 'name') -> "Dental cleaning"
     *          $pc->translate('es', 'name') -> "Limpieza dental" (original)
     *          $pc->name -> "Limpieza dental" (sin argumentos, usa original)
     *
     * Uso recomendado en el controller/service cuando se responde al frontend:
     *   $data['name'] = $pc->translate($locale, 'name');
     *
     * @param  string  $locale  Codigo de idioma (es, en, pt...)
     * @param  string  $field   Campo del modelo (name, description, requirements, etc.)
     * @return mixed
     */
    public function translate(string $locale, string $field)
    {
        // Eager-load caching: si ya se cargo el collection de translations
        // (por eager load o manualmente), buscar sin query adicional.
        if ($this->relationLoaded('translations')) {
            $translation = $this->translations->firstWhere('locale', $locale);
            if ($translation && $translation->{$field} !== null) {
                return $translation->{$field};
            }
            return $this->getAttribute($field);
        }

        // Fallback: query directa (1 query extra).
        $translation = $this->translations()
            ->where('locale', $locale)
            ->first();

        if ($translation && $translation->{$field} !== null) {
            return $translation->{$field};
        }

        return $this->getAttribute($field);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySpecialtyCode(Builder $query, ?string $specialtyCode): Builder
    {
        if (!$specialtyCode) {
            return $query;
        }
        return $query->whereHas('specialty', fn ($q) => $q->where('code', $specialtyCode));
    }

    public function scopeBySpecialtyId(Builder $query, ?int $specialtyId): Builder
    {
        if (!$specialtyId) {
            return $query;
        }
        return $query->where('specialty_id', $specialtyId);
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
