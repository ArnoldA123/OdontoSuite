<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Convenio (#293 S3).
 *
 * The agreement a procedure can be priced against; `procedure_prices` carries
 * the rows agreed with it. The code is the stable slug the tariff refers to.
 */
class Payer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function procedurePrices(): HasMany
    {
        return $this->hasMany(ProcedurePrice::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
