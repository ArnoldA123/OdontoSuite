<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #295 (S4) — what a procedure is expected to consume.
 *
 * The recipe belongs to the catalogue: an administrator declares the input and
 * the estimated quantity once. The clinical act copies it as a starting point
 * and keeps its own numbers, so a change here never rewrites a documented act.
 */
class ProcedureCatalogMaterial extends Model
{
    protected $fillable = [
        'procedure_catalog_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function procedureCatalog(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_catalog_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
