<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'treatment_plan_item_id',
        'product_id',
        'created_by',
        'quantity_used',
        'unit_cost',
        'total_cost',
        'batch_number',
        'expiry_date',
        'notes'
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'expiry_date' => 'date'
    ];

    // Relaciones
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    protected static function booted(): void
    {
        static::saving(function (ProcedureMaterial $material) {
            $qty = (float) $material->quantity_used;
            $cost = (float) $material->unit_cost;
            if (empty($material->total_cost) && $qty > 0 && $cost > 0) {
                $material->total_cost = round($qty * $cost, 2);
            }
        });
    }
}
