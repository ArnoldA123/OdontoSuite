<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientChargeRequest;
use App\Services\PatientChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * #287 (T2, S3/S4) — the counter's door to the patient ledger.
 *
 * The closure writes the charge of the act; this door records a charge no act
 * produced (an advance, a product, anything the counter agreed). It is the only
 * write surface: the ledger is never edited nor deleted, so the correction of a
 * wrong charge is a signed adjustment, not a second charge.
 */
class PatientChargeController extends Controller
{
    public function __construct(private readonly PatientChargeService $charges) {}

    public function store(StorePatientChargeRequest $request): JsonResponse
    {
        try {
            $charge = $this->charges->createManual($request->validated());

            return response()->json([
                'data' => $charge,
                'meta' => ['message' => 'Cargo registrado exitosamente'],
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PatientChargeController@store: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al registrar el cargo'], 500);
        }
    }
}
