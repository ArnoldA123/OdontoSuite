<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'requires_authorization',
        'allows_change',
        'commission_percentage',
        'is_active',
        'is_system'
    ];

    protected $casts = [
        'requires_authorization' => 'boolean',
        'allows_change' => 'boolean',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'is_system' => 'boolean'
    ];

    // Relaciones
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
