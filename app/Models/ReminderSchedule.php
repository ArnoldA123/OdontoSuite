<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use InvalidArgumentException;

class ReminderSchedule extends Model
{
    use HasFactory;

    /**
     * Slice 03 (T-03.1 + T-03.6 + T-03.7): fillable now matches the real
     * table columns (channel, error_message added) plus the historical
     * columns the model has been using (type, anticipation_hours).
     */
    protected $fillable = [
        'appointment_id',
        'reminder_template_id',
        'scheduled_at',
        'sent_at',
        'channel',
        'status',
        'type',
        'anticipation_hours',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Allowed transitions for the reminder status state machine.
     * Slice 03 (T-03.7): pending -> queued -> sent|failed, plus
     * pending -> cancelled (used by ReminderService::cancelReminders).
     */
    public const STATUS_TRANSITIONS = [
        'pending' => ['queued', 'sent', 'failed', 'cancelled'],
        'queued' => ['sent', 'failed'],
        'sent' => [],
        'failed' => ['queued'],
        'cancelled' => [],
    ];

    /**
     * Get the appointment that owns the reminder schedule.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the reminder template for the schedule.
     */
    public function reminderTemplate(): BelongsTo
    {
        return $this->belongsTo(ReminderTemplate::class);
    }

    /**
     * Scope a query to only include pending reminders.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include sent reminders.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope a query to only include reminders due for sending.
     */
    public function scopeDue($query)
    {
        return $query->where('status', 'pending')
                    ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope a query to only include reminders for a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark the reminder as sent. Kept for back-compat with ReminderService.
     */
    public function markAsSent(): void
    {
        $this->transitionTo('sent');
        $this->update(['sent_at' => now()]);
    }

    /**
     * Slice 03 (T-03.7): explicit state machine. Rejects invalid transitions
     * with InvalidArgumentException so callers must use a valid next state.
     *
     * @throws InvalidArgumentException
     */
    public function transitionTo(string $newStatus): bool
    {
        $current = $this->status ?? 'pending';

        if (!array_key_exists($current, self::STATUS_TRANSITIONS)) {
            throw new InvalidArgumentException("Unknown current status: {$current}");
        }

        $allowed = self::STATUS_TRANSITIONS[$current];

        if (!in_array($newStatus, $allowed, true) && $newStatus !== $current) {
            throw new InvalidArgumentException(
                "Invalid reminder status transition: {$current} -> {$newStatus}"
            );
        }

        $this->status = $newStatus;
        return $this->save();
    }

    /**
     * Check if the reminder is due for sending.
     */
    public function isDue(): bool
    {
        return $this->status === 'pending' && $this->scheduled_at->isPast();
    }
}
