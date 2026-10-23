<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'treatment_plan_id',
        'treatment_plan_version_id',
        'appointment_id',
        'clinical_encounter_id',
        'patient_id',
        // #293 S3/S4: the convenio the estimate was issued under.
        'payer_id',
        'created_by',
        'quotation_number',
        'quotation_date',
        'valid_until',
        'subtotal',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'total_amount',
        'status',
        'terms_conditions',
        'notes',
        'payment_terms',
        // Slice 02 / T-02.7 — optional procedure + payment method.
        'procedure_id',
        'payment_method_id',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_terms' => 'array',
    ];

    // Relaciones
    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    /**
     * #288: the immutable version the patient accepted when this quote was
     * approved.
     */
    public function treatmentPlanVersion(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanVersion::class, 'treatment_plan_version_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * F2 (#282): the clinical act that produced this quotation.
     */
    public function clinicalEncounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * #293 S3/S4: the convenio whose tariff the estimate froze.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(QuotationApproval::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
