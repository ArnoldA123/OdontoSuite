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
        'dental_piece_id',
        'created_by',
        'restoration_type',
        'material_type',
        'color_shade',
        'laboratory_name',
        'laboratory_contact',
        'impression_date',
        'try_in_date',
        'cementation_date',
        'occlusion_type',
        'bite_registration',
        'shade_selection',
        'photographic_records',
        'laboratory_notes',
        'try_in_notes',
        'cementation_notes',
        'follow_up_notes',
        'warranty_period',
        'status'
    ];

    protected $casts = [
        'impression_date' => 'date',
        'try_in_date' => 'date',
        'cementation_date' => 'date',
        'photographic_records' => 'array',
        'warranty_period' => 'integer'
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

    public function scopeByRestorationType($query, $type)
    {
        return $query->where('restoration_type', $type);
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
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInLaboratory($query)
    {
        return $query->where('status', 'in_laboratory');
    }

    public function scopeReadyForTryIn($query)
    {
        return $query->where('status', 'ready_for_try_in');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        $labels = [
            'in_progress' => 'En Progreso',
            'in_laboratory' => 'En Laboratorio',
            'ready_for_try_in' => 'Listo para Prueba',
            'try_in_scheduled' => 'Prueba Programada',
            'completed' => 'Completado',
            'failed' => 'Fallido'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getRestorationTypeLabelAttribute()
    {
        $labels = [
            'crown' => 'Corona',
            'bridge' => 'Puente',
            'veneer' => 'Carilla',
            'inlay' => 'Inlay',
            'onlay' => 'Onlay',
            'overlay' => 'Overlay'
        ];

        return $labels[$this->restoration_type] ?? $this->restoration_type;
    }
}
