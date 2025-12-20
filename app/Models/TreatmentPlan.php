<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'created_by',
        'plan_number',
        'title',
        'description',
        'status',
        'total_cost',
        'discount_amount',
        'final_cost',
        'estimated_duration_weeks',
        'start_date',
        'end_date',
        'notes',
        'patient_notes',
        'phases',
        'requires_anesthesia',
        'is_urgent'
    ];

    protected $casts = [
        'phases' => 'array',
        'requires_anesthesia' => 'boolean',
        'is_urgent' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date'
    ];

    // Relaciones
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function paymentPlans(): HasMany
    {
        return $this->hasMany(PaymentPlan::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'proposed', 'approved', 'in_progress']);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
