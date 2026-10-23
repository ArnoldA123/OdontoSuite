<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * F6 (#286) — the CIE-10 catalogue.
 *
 * `diagnosis_catalog` existed since the first schema without a model: the column
 * `cie_code` was there, nothing read it. This model gives the table the read
 * surface the clinical record needs, and the act copies the code and the name
 * into `encounter_diagnoses` so a signed record never depends on a later edit.
 */
class DiagnosisCatalog extends Model
{
    use HasFactory;

    protected $table = 'diagnosis_catalog';

    protected $fillable = [
        'cie_code',
        'name',
        'description',
        'category',
        'specialty',
        'symptoms',
        'treatment_guidelines',
        'prevention_measures',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function diagnoses(): HasMany
    {
        return $this->hasMany(EncounterDiagnosis::class, 'diagnosis_catalog_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Search by code or name. The term is escaped so a `%` typed by the user is
     * a literal and not a wildcard of the query.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('cie_code', 'like', $like)
                ->orWhere('name', 'like', $like);
        });
    }
}
