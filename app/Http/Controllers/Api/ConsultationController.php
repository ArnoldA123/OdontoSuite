<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Consultation\ConsultationException;
use App\Exceptions\Encounter\EncounterException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseClinicalEncounterRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\TreatmentPlan;
use App\Services\ConsultationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ConsultationController extends Controller
{
    public function __construct(private readonly ConsultationService $consultations) {}

    /**
     * GET /api/appointments/{id}/consultation-context.
     *
     * Devuelve el contexto que el wizard necesita para renderizarse:
     * la cita, la HC del paciente, planes activos, y datos auxiliares.
     */
    public function context(Appointment $appointment): JsonResponse
    {
        $appointment->load([
            'patient',
            'user',
            'appointmentType',
            'dentalChair',
            'treatmentPlan',
            'clinicalEvolutions',
            'odontogramRecords.dentalPiece',
            'procedureMaterials.product',
        ]);

        $activePlans = TreatmentPlan::with(['items'])
            ->where('patient_id', $appointment->patient_id)
            ->whereIn('status', ['draft', 'proposed', 'approved', 'in_progress'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'plan_number' => $plan->plan_number,
                'title' => $plan->title,
                'status' => $plan->status,
                'progress' => $plan->progressMetrics(),
                'items' => $plan->items->map(fn ($i) => [
                    'id' => $i->id,
                    'procedure_name' => $i->procedure_name,
                    'specialty' => $i->specialty,
                    'dental_piece_id' => $i->dental_piece_id,
                    'phase_number' => $i->phase_number,
                    'unit_cost' => (float) $i->unit_cost,
                    'total_cost' => (float) $i->total_cost,
                    'status' => $i->status,
                    'materials_required' => $i->requiredMaterialsList(),
                ]),
            ]);

        return response()->json([
            'data' => [
                'appointment' => (new AppointmentResource($appointment))->resolve(),
                'active_plans' => $activePlans,
                'requires_materials' => (bool) $appointment->appointmentType?->requires_materials,
                'appointment_type' => [
                    'id' => $appointment->appointmentType?->id,
                    'name' => $appointment->appointmentType?->name,
                    'price' => (float) ($appointment->appointmentType?->price ?? 0),
                    'default_duration_minutes' => $appointment->appointmentType?->default_duration_minutes,
                    'is_consultation_mode' => (bool) $appointment->appointmentType?->is_consultation_mode,
                ],
            ],
        ]);
    }

    /**
     * POST /api/appointments/{id}/complete.
     *
     * F2 (#282): the appointment door of the single closure. The act of the
     * appointment is resolved (or opened) by the service and closed there; the
     * appointment receives its projection in the same transaction.
     */
    public function complete(CloseClinicalEncounterRequest $request, Appointment $appointment): JsonResponse
    {
        try {
            $result = $this->consultations->complete($appointment, $request->validated());
            $completedAppointment = $result['appointment'];
            $quotation = $result['quotation'];

            return response()->json([
                'data' => (new AppointmentResource($completedAppointment))->resolve(),
                'quotation' => $quotation
                    ? $quotation->loadMissing(['items', 'patient', 'treatmentPlan'])
                    : null,
                'meta' => [
                    'message' => 'Consulta completada.',
                    'final_amount' => (float) $completedAppointment->final_amount,
                    'consultation_mode' => $completedAppointment->consultation_mode,
                    'encounter_id' => $result['encounter']->id,
                    'quotation_generated' => (bool) $quotation,
                    'quotation_id' => $quotation?->id,
                ],
            ]);
        } catch (ConsultationException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        } catch (EncounterException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        } catch (\Throwable $e) {
            Log::error('Consultation complete failed', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al completar la consulta.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
