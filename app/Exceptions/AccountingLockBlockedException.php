<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #304 (S4/S8) — the money modal needs to recognise the accounting lock
 * without reading the copy of the message.
 *
 * The 403 keeps the message the service wrote and publishes the machine code
 * the SPA keys on, so the notice offers the override instead of treating the
 * refusal as a permission error. The status stays 403: the operation is not
 * allowed without a registered authorization.
 */
class AccountingLockBlockedException extends AuthorizationException
{
    public const CODE = 'accounting_lock_blocked';

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], 403);
    }
}
