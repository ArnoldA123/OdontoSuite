<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'patient_id',
        'approval_status',
        'patient_notes',
        'modifications_requested',
        'approval_date',
        'approval_method',
        'signature_data',
        'approved_items'
    ];

    protected $casts = [
        'approval_date' => 'date',
        'approved_items' => 'array'
    ];

    // Relaciones
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('approval_status', $status);
    }
}
