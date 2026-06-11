<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 4 fix (IM-8): traducciones de un procedimiento del catalogo.
 * Un procedimiento puede tener una traduccion por locale (es, en, pt...).
 * El accessor ProcedureCatalog::name($locale) resuelve el valor correcto.
 */
class ProcedureCatalogTranslation extends Model
{
    use HasFactory;

    protected $table = 'procedure_catalog_translations';

    protected $fillable = [
        'procedure_catalog_id',
        'locale',
        'name',
        'description',
        'requirements',
        'materials_needed',
        'contraindications',
        'post_procedure_care',
    ];

    public function procedureCatalog(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_catalog_id');
    }
}
