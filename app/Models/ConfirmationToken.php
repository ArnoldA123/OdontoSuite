<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ConfirmationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'token',
        'type',
        'expires_at',
        'used_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the appointment that owns the confirmation token.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Scope a query to only include valid (non-expired, non-used) tokens.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include tokens of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Create a new confirmation token.
     */
    public static function createForAppointment(Appointment $appointment, string $type, int $expirationHours = 24): self
    {
        return self::create([
            'appointment_id' => $appointment->id,
            'token' => Str::random(64),
            'type' => $type,
            'expires_at' => now()->addHours($expirationHours),
        ]);
    }

    /**
     * Check if the token is valid (not expired and not used).
     */
    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }

    /**
     * Mark the token as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    /**
     * Get the confirmation URL for this token.
     */
    public function getConfirmationUrl(): string
    {
        return route('appointments.confirm', ['token' => $this->token]);
    }

    /**
     * Get the reschedule URL for this token.
     */
    public function getRescheduleUrl(): string
    {
        return route('appointments.reschedule', ['token' => $this->token]);
    }

    /**
     * Get the cancellation URL for this token.
     */
    public function getCancellationUrl(): string
    {
        return route('appointments.cancel', ['token' => $this->token]);
    }
}
