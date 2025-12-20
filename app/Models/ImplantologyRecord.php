<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplantologyRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'dental_piece_id',
        'created_by',
        'implant_brand',
        'implant_model',
        'implant_diameter',
        'implant_length',
        'batch_number',
        'serial_number',
        'placement_date',
        'healing_date',
        'loading_date',
        'status',
        'surgical_notes',
        'post_surgical_notes',
        'complications',
        'radiographic_data',
        'measurements',
        'torque_value',
        'follow_up_notes'
    ];

    protected $casts = [
        'placement_date' => 'date',
        'healing_date' => 'date',
        'loading_date' => 'date',
        'radiographic_data' => 'array',
        'measurements' => 'array',
        'torque_value' => 'decimal:2'
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

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDentalPiece($query, $dentalPieceId)
    {
        return $query->where('dental_piece_id', $dentalPieceId);
    }

    public function scopePlaced($query)
    {
        return $query->where('status', 'placed');
    }

    public function scopeHealing($query)
    {
        return $query->where('status', 'healing');
    }

    public function scopeLoaded($query)
    {
        return $query->where('status', 'loaded');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        $labels = [
            'placed' => 'Colocado',
            'healing' => 'Cicatrizando',
            'loaded' => 'Cargado',
            'failed' => 'Fallido',
            'removed' => 'Removido'
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
