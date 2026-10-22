<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F1 (#281) — append-only custody trail.
 *
 * Every transfer between desks (reception → professional) records the action,
 * the door that received the custody, the author and the hour. `created_at`
 * is the occurrence: a log row is never edited.
 */
class AppointmentCustodyLog extends Model
{
    use HasFactory;

    public const ACTION_ARRIVAL = 'arrival';

    public const ACTION_ATTENTION_START = 'attention_start';

    public const DOOR_RECEPTION = 'reception';

    public const DOOR_PROFESSIONAL = 'professional';

    public const UPDATED_AT = null;

    protected $fillable = [
        'appointment_id',
        'action',
        'door',
        'from_status',
        'to_status',
        'actor_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
