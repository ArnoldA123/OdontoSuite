<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleContinuityRequest;
use App\Models\ContinuityRequest;
use App\Services\ContinuityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * F4 (#284) — the reception desk of continuity.
 *
 * One read surface answers what is waiting (overdue recalls plus the conduct
 * the professional left), and one write surface materialises it through the
 * agenda. It never books directly: `ContinuityService` delegates to
 * `AppointmentService`, so a control appointment validates conflicts exactly
 * like any other.
 */
class ContinuityController extends Controller
{
    public function __construct(private readonly ContinuityService $continuity) {}

    public function queue(Request $request): JsonResponse
    {
        $requested = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $branchId = BranchAccess::effectiveFilter($request->user(), $requested);

        try {
            $data = $this->continuity->forDesk($branchId);
        } catch (\Exception $e) {
            Log::error('ContinuityController@queue: '.$e->getMessage());

            return response()->json(['message' => 'Error al obtener la cola de continuidad'], 500);
        }

        return response()->json([
            'data' => $data,
            'meta' => ['date' => now()->toDateString()],
        ]);
    }

    public function show(ContinuityRequest $continuityRequest): JsonResponse
    {
        $continuityRequest->load(['patient', 'appointmentType', 'clinicalEncounter', 'treatmentPlan', 'scheduledAppointment']);

        return response()->json(['data' => $continuityRequest]);
    }

    public function schedule(ScheduleContinuityRequest $request, ContinuityRequest $continuityRequest): JsonResponse
    {
        try {
            $result = $this->continuity->schedule($continuityRequest, $request->validated(), $request->user());
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ContinuityController@schedule: '.$e->getMessage());

            return response()->json(['message' => 'Error al agendar la cita de continuidad'], 500);
        }

        return response()->json([
            'data' => [
                'request' => $result['request']->load(['patient', 'scheduledAppointment']),
                'appointment' => $result['appointment']->load(['patient', 'user', 'dentalChair', 'appointmentType']),
            ],
            'meta' => ['message' => 'Cita de continuidad agendada exitosamente'],
        ], 201);
    }

    public function dismiss(Request $request, ContinuityRequest $continuityRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'sometimes|nullable|string|max:1000',
        ]);

        try {
            $dismissed = $this->continuity->dismiss($continuityRequest, $validated['reason'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ContinuityController@dismiss: '.$e->getMessage());

            return response()->json(['message' => 'Error al descartar la solicitud de continuidad'], 500);
        }

        return response()->json([
            'data' => $dismissed,
            'meta' => ['message' => 'Solicitud de continuidad descartada exitosamente'],
        ]);
    }
}
