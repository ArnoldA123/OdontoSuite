<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OralSurgeryRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'dental_piece_id',
        'created_by',
        'procedure_type',
        'surgery_date',
        'surgery_duration_minutes',
        'anesthesia_type',
        'anesthesia_duration_minutes',
        'surgical_technique',
        'incision_type',
        'suture_material',
        'suture_technique',
        'suture_count',
        'complications',
        'bleeding_control',
        'post_surgical_instructions',
        'medications_prescribed',
        'follow_up_schedule',
        'healing_assessment',
        'suture_removal_date',
        'final_assessment',
        'status'
    ];

    protected $casts = [
        'surgery_date' => 'date',
        'suture_removal_date' => 'date',
        'surgery_duration_minutes' => 'integer',
        'anesthesia_duration_minutes' => 'integer',
        'suture_count' => 'integer'
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

    public function scopeByProcedureType($query, $type)
    {
        return $query->where('procedure_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInRecovery($query)
    {
        return $query->where('status', 'in_recovery');
    }

    public function scopeWithComplications($query)
    {
        return $query->whereNotNull('complications');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        $labels = [
            'scheduled' => 'Programada',
            'in_progress' => 'En Progreso',
            'completed' => 'Completada',
            'in_recovery' => 'En Recuperación',
            'follow_up' => 'Seguimiento',
            'healed' => 'Curada'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getProcedureTypeLabelAttribute()
    {
        $labels = [
            'extraction' => 'Extracción',
            'implant_placement' => 'Colocación de Implante',
            'biopsy' => 'Biopsia',
            'cyst_removal' => 'Remoción de Quiste',
            'apicectomy' => 'Apicectomía',
            'gum_surgery' => 'Cirugía de Encía',
            'bone_graft' => 'Injerto Óseo'
        ];

        return $labels[$this->procedure_type] ?? $this->procedure_type;
    }

    public function getSurgeryDurationFormattedAttribute()
    {
        $hours = floor($this->surgery_duration_minutes / 60);
        $minutes = $this->surgery_duration_minutes % 60;

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }

        return $minutes . ' minutos';
    }
}
