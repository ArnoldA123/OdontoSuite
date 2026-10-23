<?php

namespace App\Models;

use App\Models\Concerns\ScopesToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F4 (#284) — the conduct the professional leaves at the close of an act.
 *
 * A continuity request is not an appointment: it records what the patient
 * needs next (a plan session, a control, a recall) and lets reception
 * materialise it through the agenda door, which is the only path that validates
 * conflicts. The wizard used to invent the next appointment directly; that is
 * what this entity replaces.
 */
class ContinuityRequest extends Model
{
    use HasFactory, ScopesToBranch;

    public const KIND_NEXT_SESSION = 'next_session';

    public const KIND_CONTROL = 'control';

    public const KIND_RECALL = 'recall';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'patient_id',
        'branch_id',
        'clinical_encounter_id',
        'treatment_plan_id',
        'appointment_id',
        'appointment_type_id',
        'kind',
        'status',
        'preferred_from',
        'preferred_to',
        'duration_minutes',
        'notes',
        'scheduled_appointment_id',
        'resolution_notes',
        'created_by',
    ];

    protected $casts = [
        'preferred_from' => 'datetime',
        'preferred_to' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function clinicalEncounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function scheduledAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'scheduled_appointment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
