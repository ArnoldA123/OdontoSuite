<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientChargeAllocationRequest;
use App\Models\Transaction;
use App\Services\PatientChargeAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * #321 (S1-S4) — the door that imputes an already collected payment.
 *
 * The payment is created at the counter and the charges of the act may arrive
 * before or after it, so the imputation has its own door instead of living only
 * in the payment payload. Writing it is counter work like collecting
 * (`manage.patient_charge`); the ledger is of the patient, so the door is not
 * branch-scoped (ADR-0010).
 */
class PatientChargeAllocationController extends Controller
{
    public function __construct(private readonly PatientChargeAllocationService $allocations) {}

    public function store(StorePatientChargeAllocationRequest $request, Transaction $transaction): JsonResponse
    {
        try {
            $created = $this->allocations->allocate($transaction, $request->validated()['allocations']);

            return response()->json([
                'data' => array_map(
                    fn ($allocation) => $allocation->load(['patientCharge', 'transaction']),
                    $created,
                ),
                'meta' => ['message' => 'Imputación registrada exitosamente'],
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PatientChargeAllocationController@store: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al imputar el pago a los cargos'], 500);
        }
    }
}
