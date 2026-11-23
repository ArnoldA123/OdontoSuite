<?php

namespace App\Exceptions;

use App\Models\Appointment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Another active appointment already owns the requested slot pair.
 *
 * It stays a ValidationException so every door keeps its 422 and its
 * `errors.scheduled_at` entry; the extra payload lets a door that knows the
 * blocking row publish the same `conflict` envelope as the plain pre-check.
 */
class AppointmentSlotConflictException extends ValidationException
{
    public string $conflictType = 'slot_taken';

    public ?Appointment $conflictingAppointment = null;

    /**
     * @param Collection<int, Appointment> $conflicts
     */
    public static function forConflicts(Collection $conflicts, int $userId): self
    {
        $first = $conflicts->first();

        $exception = static::withMessages([
            'scheduled_at' => ['Conflicto de horario detectado. Ya existe una cita en ese horario.'],
        ]);

        $exception->conflictType = $first !== null && (int) $first->user_id === $userId
            ? 'user_conflict'
            : 'chair_conflict';
        $exception->conflictingAppointment = $first;

        return $exception;
    }
}
