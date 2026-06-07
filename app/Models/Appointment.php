<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'user_id',
        'dental_chair_id',
        'appointment_type_id',
        'scheduled_at',
        'ends_at',
        'duration_minutes',
        'status',
        'notes',
        'treatment_notes',
        'idempotency_key',
        'treatment_plan_id',
        'final_amount',
        'consultation_mode',
        'checked_in_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'ends_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'completed_at' => 'datetime',
        'final_amount' => 'decimal:2',
    ];

    /**
     * Get the has payment attribute.
     */
    public function getHasPaymentAttribute()
    {
        return $this->transactions()
            ->where('type', 'payment')
            ->where('status', '!=', 'voided')
            ->exists();
    }

    /**
     * Get the patient that owns the appointment.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the dentist/professional that owns the appointment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the dental chair for the appointment.
     */
    public function dentalChair(): BelongsTo
    {
        return $this->belongsTo(DentalChair::class);
    }

    /**
     * Get the appointment type for the appointment.
     */
    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    /**
     * Get the user who created the appointment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the appointment.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the recurrence for the appointment.
     */
    public function recurrence(): HasOne
    {
        return $this->hasOne(AppointmentRecurrence::class);
    }

    /**
     * Get the reminder schedules for the appointment.
     */
    public function reminderSchedules(): HasMany
    {
        return $this->hasMany(ReminderSchedule::class);
    }

    /**
     * Get the confirmation tokens for the appointment.
     */
    public function confirmationTokens(): HasMany
    {
        return $this->hasMany(ConfirmationToken::class);
    }

    /**
     * Scope a query to only include appointments for a specific date.
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('scheduled_at', $date);
    }

    /**
     * Scope a query to only include appointments for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include appointments with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if the appointment is in the past.
     */
    public function isPast(): bool
    {
        return $this->scheduled_at->isPast();
    }

    /**
     * Check if the appointment is today.
     */
    public function isToday(): bool
    {
        return $this->scheduled_at->isToday();
    }

    /**
     * Check if the appointment is tomorrow.
     */
    public function isTomorrow(): bool
    {
        return $this->scheduled_at->isTomorrow();
    }

    /**
     * Get the transactions for the appointment.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Plan de tratamiento creado o avanzado en esta cita.
     */
    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    /**
     * Evoluciones clínicas asociadas a esta cita.
     */
    public function clinicalEvolutions(): HasMany
    {
        return $this->hasMany(ClinicalEvolution::class);
    }

    /**
     * Materiales consumidos durante esta cita.
     */
    public function procedureMaterials(): HasMany
    {
        return $this->hasMany(ProcedureMaterial::class);
    }

    /**
     * Registros de odontograma hechos durante esta cita.
     */
    public function odontogramRecords(): HasMany
    {
        return $this->hasMany(OdontogramRecord::class);
    }

    /**
     * ¿La cita está en modo consulta clínica (atendiendo)?
     */
    public function isInConsultation(): bool
    {
        return $this->status === 'in_consultation';
    }

    /**
     * ¿La cita ya fue completada y cerrada?
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
