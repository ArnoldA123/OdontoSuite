<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * #295 S6: ventana con la que un lote se considera proximo a vencer.
     * Vive aca porque la alerta es una propiedad del producto, y el servicio y
     * el endpoint de alertas tienen que mirar el mismo numero.
     */
    public const EXPIRING_SOON_DAYS = 30;

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
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        // #295 S8: el stock es una cantidad, no un contador entero. 0.5 jeringa
        // y 12.5 ml tienen que sobrevivir al viaje por la base.
        'stock_quantity' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'maximum_stock' => 'decimal:3',
        'expiry_date' => 'date',
        'specifications' => 'array',
        'requires_prescription' => 'boolean',
        'is_active' => 'boolean',
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

    /**
     * #295 S4: recetas de catalogo que declaran este insumo.
     */
    public function procedureCatalogMaterials(): HasMany
    {
        return $this->hasMany(ProcedureCatalogMaterial::class);
    }

    // Estado del stock (#295 S6)
    public function isOutOfStock(): bool
    {
        return (float) $this->stock_quantity <= 0.0;
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->minimum_stock;
    }

    public function isExpiringSoon(?int $days = null): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        $window = $days ?? self::EXPIRING_SOON_DAYS;

        return $this->expiry_date->lessThanOrEqualTo(now()->addDays($window)->startOfDay());
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
