<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'treatment_plan_item_id',
        'procedure_catalog_id',
        'dental_piece_id',
        'tooth_surface_id',
        'site_code',
        'item_name',
        'item_description',
        'quantity',
        'unit_price',
        'total_price',
        'specialty',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // Relaciones
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_catalog_id');
    }

    public function dentalPiece(): BelongsTo
    {
        return $this->belongsTo(DentalPiece::class);
    }

    public function toothSurface(): BelongsTo
    {
        return $this->belongsTo(ToothSurface::class);
    }
}
