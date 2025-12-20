<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DentalPiece extends Model
{
    use HasFactory;

    protected $fillable = [
        'fdi_number',
        'name',
        'type',
        'quadrant',
        'position',
        'is_permanent',
        'description',
        'surfaces',
        'is_active'
    ];

    protected $casts = [
        'surfaces' => 'array',
        'is_permanent' => 'boolean',
        'is_active' => 'boolean'
    ];

    // Relaciones
    public function toothSurfaces(): HasMany
    {
        return $this->hasMany(ToothSurface::class);
    }

    public function odontogramRecords(): HasMany
    {
        return $this->hasMany(OdontogramRecord::class);
    }

    public function treatmentPlanItems(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePermanent($query)
    {
        return $query->where('is_permanent', true);
    }

    public function scopeTemporary($query)
    {
        return $query->where('is_permanent', false);
    }
}
