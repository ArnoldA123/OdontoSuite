<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RehabilitationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'procedure_id',
        'dental_piece_id',
        'created_by',
        'prosthesis_type',
        'material_type',
        'laboratory_name',
        'laboratory_contact',
        'impression_date',
        'delivery_date',
        'cementation_date',
        'shade_selection',
        'impression_notes',
        'try_in_notes',
        'adjustment_notes',
        'cementation_notes',
        'patient_satisfaction',
        'complications',
        'measurements',
        'status',
        'follow_up_notes',
    ];

    protected $casts = [
        'impression_date' => 'date',
        'delivery_date' => 'date',
        'cementation_date' => 'date',
        'measurements' => 'array',
    ];

    // Relaciones
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Catalog procedure this record executed (issue #247).
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_id');
    }

    public function dentalPiece(): BelongsTo
    {
        return $this->belongsTo(DentalPiece::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByProsthesisType($query, $type)
    {
        return $query->where('prosthesis_type', $type);
    }

    public function scopeByMaterialType($query, $material)
    {
        return $query->where('material_type', $material);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['impression', 'laboratory', 'try_in']);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['delivered', 'cemented']);
    }

    public function scopeInLaboratory($query)
    {
        return $query->where('status', 'laboratory');
    }

    public function scopeReadyForTryIn($query)
    {
        return $query->where('status', 'try_in');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        $labels = [
            'impression' => 'Impresión',
            'laboratory' => 'En Laboratorio',
            'try_in' => 'En Prueba',
            'delivered' => 'Entregada',
            'cemented' => 'Cementada',
            'failed' => 'Fallida',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getProsthesisTypeLabelAttribute()
    {
        $labels = [
            'crown' => 'Corona',
            'bridge' => 'Puente',
            'partial' => 'Prótesis parcial',
            'complete' => 'Prótesis completa',
            'implant_prosthesis' => 'Prótesis sobre implante',
        ];

        return $labels[$this->prosthesis_type] ?? $this->prosthesis_type;
    }
}
