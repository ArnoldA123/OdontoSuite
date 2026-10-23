<?php

namespace App\Models;

use App\Models\Concerns\ScopesToBranch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F4 (#284, S1/S4) — the recall of a patient.
 *
 * The due date is the truth of the recall: a pending row whose `due_date` has
 * arrived is overdue, and reception turns it into a control appointment through
 * the agenda door. The row keeps the appointment it produced, so the recall is
 * never a second copy of the appointment.
 */
class PatientRecall extends Model
{
    use HasFactory, ScopesToBranch;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const INTERVALS = [3, 6, 12];

    protected $fillable = [
        'patient_id',
        'branch_id',
        'interval_months',
        'reason',
        'due_date',
        'status',
        'scheduled_appointment_id',
        'completed_at',
        'cancelled_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'interval_months' => 'integer',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function scheduledAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'scheduled_appointment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDue(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->due_date !== null
            && $this->due_date->lessThanOrEqualTo(Carbon::today());
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->pending()
            ->whereDate('due_date', '<=', Carbon::today()->toDateString());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
