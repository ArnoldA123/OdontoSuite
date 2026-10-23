<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #293 S7: equivalencia de un procedimiento con un nomenclador externo
 * (catálogo RM 550-2023/MINSA, tarifario del SIS u otro financiador).
 * El conjunto se define completo en el alta y la edición del procedimiento.
 */
class ProcedureExternalCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'procedure_catalog_id',
        'system',
        'code',
        'description',
    ];

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_catalog_id');
    }
}
