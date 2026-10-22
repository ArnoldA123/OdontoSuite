<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Custody\CustodyException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentCustodyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F1 (#281) — the custody doors.
 *
 * Each act has one endpoint and one permission: reception registers the
 * arrival, reception announces the patient, the professional starts the
 * attention, and the professional's desk reads its own queue.
 */
class AppointmentCustodyController extends Controller
{
    private const RELATIONS = ['patient', 'user', 'appointmentType', 'dentalChair', 'checkedInBy'];

    public function __construct(private readonly AppointmentCustodyService $custody) {}

    public function checkIn(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->custody->markArrival($appointment, $request->user());
        } catch (CustodyException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        }

        return response()->json([
            'data' => $this->resource($appointment),
            'meta' => ['message' => 'Llegada registrada.'],
        ]);
    }

    public function announce(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->custody->announce($appointment, $request->user());
        } catch (CustodyException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        }

        return response()->json([
            'data' => $this->resource($appointment),
            'meta' => ['message' => 'Paciente anunciado.'],
        ]);
    }

    public function startAttention(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->custody->startAttention($appointment, $request->user());
        } catch (CustodyException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        }

        return response()->json([
            'data' => $this->resource($appointment),
            'meta' => ['message' => 'Atención iniciada.'],
        ]);
    }

    public function myDay(Request $request): JsonResponse
    {
        $queue = $this->custody->queueFor($request->user());

        return response()->json([
            'data' => [
                'waiting' => AppointmentResource::collection($queue['waiting'])->toArray($request),
                'attending' => AppointmentResource::collection($queue['attending'])->toArray($request),
                'upcoming' => AppointmentResource::collection($queue['upcoming'])->toArray($request),
            ],
            'meta' => [
                'date' => now()->toDateString(),
                'counts' => [
                    'waiting' => $queue['waiting']->count(),
                    'attending' => $queue['attending']->count(),
                    'upcoming' => $queue['upcoming']->count(),
                ],
            ],
        ]);
    }

    private function resource(Appointment $appointment): array
    {
        return (new AppointmentResource($appointment->loadMissing(self::RELATIONS)))->resolve();
    }
}
