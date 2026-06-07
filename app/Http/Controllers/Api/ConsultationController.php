<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Consultation\ConsultationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\TreatmentPlan;
use App\Services\ConsultationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConsultationController extends Controller
{
    public function __construct(private readonly ConsultationService $consultations)
    {
    }

    /**
     * GET /api/appointments/{id}/consultation-context
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
     * POST /api/appointments/{id}/check-in
     */
    public function checkIn(Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->consultations->checkIn($appointment);
            return response()->json([
                'data' => (new AppointmentResource($appointment->load([
                    'patient', 'user', 'appointmentType', 'dentalChair',
                ])))->resolve(),
                'meta' => ['message' => 'Paciente registrado en consulta.'],
            ]);
        } catch (ConsultationException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        }
    }

    /**
     * POST /api/appointments/{id}/complete
     */
    public function complete(Request $request, Appointment $appointment): JsonResponse
    {
        $payload = $request->validate([
            'mode' => 'required|in:consultation,execution,plan_session',
            'skip_materials' => 'sometimes|boolean',
            'evolution' => 'required|array',
            'evolution.subjective' => 'required|string',
            'evolution.objective' => 'required|string',
            'evolution.assessment' => 'required|string',
            'evolution.plan' => 'required|string',
            'evolution.specialty' => 'nullable|string',
            'evolution.procedures_performed' => 'nullable|string',
            'evolution.materials_used' => 'nullable|string',
            'evolution.prescriptions' => 'nullable|string',
            'evolution.recommendations' => 'nullable|string',
            'evolution.next_appointment_notes' => 'nullable|string',
            'evolution.vital_signs' => 'nullable|array',
            'evolution.clinical_measurements' => 'nullable|array',
            'evolution.requires_follow_up' => 'sometimes|boolean',
            'evolution.follow_up_date' => 'nullable|date',
            'odontogram' => 'sometimes|array',
            'odontogram.*.dental_piece_id' => 'required_with:odontogram|integer',
            'odontogram.*.condition' => 'nullable|string',
            'odontogram.*.condition_code' => 'nullable|string',
            'odontogram.*.condition_name' => 'nullable|string',
            'odontogram.*.diagnosis' => 'nullable|string',
            'odontogram.*.notes' => 'nullable|string',
            'odontogram.*.surfaces' => 'nullable|array',
            'odontogram.*.color' => 'nullable|string',
            'treatment_plan' => 'sometimes|array',
            'treatment_plan.id' => 'sometimes|integer',
            'treatment_plan.create_new' => 'sometimes|boolean',
            'treatment_plan.title' => 'sometimes|string|max:255',
            'treatment_plan.description' => 'nullable|string',
            'treatment_plan.notes' => 'nullable|string',
            'treatment_plan.as_proposed' => 'sometimes|boolean',
            'treatment_plan.items' => 'sometimes|array',
            'treatment_plan.items.*.id' => 'sometimes|integer',
            'treatment_plan.items.*.procedure_name' => 'required_with:items|string',
            'treatment_plan.items.*.dental_piece_id' => 'nullable|integer',
            'treatment_plan.items.*.specialty' => 'nullable|string',
            'treatment_plan.items.*.unit_cost' => 'nullable|numeric|min:0',
            'treatment_plan.items.*.quantity' => 'nullable|integer|min:1',
            'treatment_plan.items.*.phase_number' => 'nullable|integer|min:1',
            'treatment_plan.items.*.estimated_duration_minutes' => 'nullable|integer',
            'treatment_plan.items.*.status' => 'sometimes|string',
            'treatment_plan.items.*.materials_required' => 'nullable',
            'treatment_plan.items.*.requires_anesthesia' => 'sometimes|boolean',
            'treatment_plan.items.*.is_optional' => 'sometimes|boolean',
            'materials' => 'sometimes|array',
            'materials.*.product_id' => 'required_with:materials|integer',
            'materials.*.quantity_used' => 'required_with:materials|numeric|min:0.01',
            'materials.*.unit_cost' => 'nullable|numeric|min:0',
            'materials.*.treatment_plan_item_id' => 'nullable|integer',
            'materials.*.batch_number' => 'nullable|string',
            'materials.*.expiry_date' => 'nullable|date',
            'materials.*.notes' => 'nullable|string',
            'attachments' => 'sometimes|array',
            'attachments.*.category' => 'nullable|string',
            'attachments.*.description' => 'nullable|string',
            'attachments.*.is_private' => 'sometimes|boolean',
            'attachments.*.file' => 'required_with:attachments|file|max:10240',
            'next_appointment' => 'sometimes|array',
            'next_appointment.scheduled_at' => 'required_with:next_appointment|date',
            'next_appointment.appointment_type_id' => 'nullable|integer',
            'next_appointment.duration_minutes' => 'nullable|integer|min:15',
            'next_appointment.notes' => 'nullable|string',
        ]);

        try {
            $appointment = $this->consultations->complete($appointment, $payload);
            return response()->json([
                'data' => (new AppointmentResource($appointment))->resolve(),
                'meta' => [
                    'message' => 'Consulta completada.',
                    'final_amount' => (float) $appointment->final_amount,
                    'consultation_mode' => $appointment->consultation_mode,
                ],
            ]);
        } catch (ConsultationException $e) {
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
