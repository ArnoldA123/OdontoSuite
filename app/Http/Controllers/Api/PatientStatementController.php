<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\PatientStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * #287 (T3, S3/S6/S9) — the patient statement as reception reads it.
 *
 * The period defaults to the current month through today, and both dates are
 * inclusive. The statement is read-only: the ledger is corrected with a signed
 * adjustment, never by rewriting the book (#287, D1).
 */
class PatientStatementController extends Controller
{
    public function __construct(private readonly PatientStatementService $statements) {}

    public function show(Request $request, Patient $patient): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ], [
            'date_from.date' => 'La fecha inicial debe ser una fecha válida.',
            'date_to.date' => 'La fecha final debe ser una fecha válida.',
        ]);

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        if ($dateFrom !== null && $dateTo !== null
            && Carbon::parse($dateFrom)->startOfDay()->gt(Carbon::parse($dateTo)->startOfDay())) {
            throw ValidationException::withMessages([
                'date_to' => ['La fecha final no puede ser anterior a la fecha inicial.'],
            ]);
        }

        try {
            return response()->json([
                'data' => $this->statements->forPatient($patient, $dateFrom, $dateTo),
            ]);
        } catch (\Throwable $e) {
            Log::error('PatientStatementController@show: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al generar el estado de cuenta'], 500);
        }
    }
}
