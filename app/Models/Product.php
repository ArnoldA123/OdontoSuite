<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_category_id',
        'name',
        'code',
        'barcode',
        'description',
        'unit',
        'cost_price',
        'sale_price',
        'stock_quantity',
        'minimum_stock',
        'maximum_stock',
        'supplier',
        'brand',
        'model',
        'expiry_date',
        'storage_conditions',
        'specifications',
        'requires_prescription',
        'is_active'
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'expiry_date' => 'date',
        'specifications' => 'array',
        'requires_prescription' => 'boolean',
        'is_active' => 'boolean'
    ];

    // Relaciones
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function procedureMaterials(): HasMany
    {
        return $this->hasMany(ProcedureMaterial::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock_quantity <= minimum_stock');
    }
}
