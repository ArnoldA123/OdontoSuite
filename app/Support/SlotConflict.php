<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * The engine's answer when a slot pair is already reserved.
 *
 * The pre-check reads the agenda, but a row the model no longer sees — a
 * soft-deleted appointment before the release migration, or a write that
 * commits between the read and the insert — can still own the unique key. The
 * door that receives that driver error answers the agenda contract, never a
 * 500: 422 with `conflict.type = slot_taken`.
 */
final class SlotConflict
{
    public const MESSAGE = 'Ese horario ya está ocupado por otra cita activa. Actualiza la agenda y vuelve a intentarlo.';

    /**
     * A duplicate key on one of the two slot indexes, however it is wrapped.
     * The index name is the discriminator: the idempotency key is unique too,
     * and a collision there is not a slot conflict.
     */
    public static function isCollision(\Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $message = $current->getMessage();

            if (str_contains($message, 'unique_user_time_slot')
                || str_contains($message, 'unique_chair_time_slot')) {
                return true;
            }
        }

        return false;
    }

    public static function response(\Throwable $exception): ?JsonResponse
    {
        if (! self::isCollision($exception)) {
            return null;
        }

        return response()->json([
            'message' => self::MESSAGE,
            'errors' => [
                'scheduled_at' => [self::MESSAGE],
            ],
            'conflict' => [
                'type' => 'slot_taken',
                'conflicting_appointment' => null,
            ],
        ], 422);
    }
}
