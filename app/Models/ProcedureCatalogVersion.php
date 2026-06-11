<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 3 fix (IM-7): snapshot inmutable de cambios en procedure_catalog.
 * Se crea automaticamente al cambiar default_cost, name o is_active.
 */
class ProcedureCatalogVersion extends Model
{
    use HasFactory;

    protected $table = 'procedure_catalog_versions';

    protected $fillable = [
        'procedure_catalog_id',
        'changed_by',
        'change_type',
        'changed_fields',
        'default_cost',
        'name',
        'code',
        'is_active',
        'changed_at',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'default_cost' => 'decimal:2',
        'is_active' => 'boolean',
        'changed_at' => 'datetime',
    ];

    public function procedureCatalog(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_catalog_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
