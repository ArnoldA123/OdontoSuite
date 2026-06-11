<?php

namespace App\Services;

use App\Events\AppointmentCompleted;
use App\Exceptions\Consultation\AppointmentNotInConsultationException;
use App\Exceptions\Consultation\InvalidConsultationModeException;
use App\Exceptions\Consultation\InvalidTreatmentPlanException;
use App\Exceptions\Consultation\MissingEvolutionException;
use App\Exceptions\Consultation\MissingMaterialsException;
use App\Exceptions\Consultation\UnexpectedMaterialsException;
use App\Models\Appointment;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\Odontogram;
use App\Models\OdontogramRecord;
use App\Models\Patient;
use App\Models\ProcedureMaterial;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConsultationService
{
    public const MODE_CONSULTATION = 'consultation';
    public const MODE_EXECUTION = 'execution';
    public const MODE_PLAN_SESSION = 'plan_session';

    public const VALID_MODES = [
        self::MODE_CONSULTATION,
        self::MODE_EXECUTION,
        self::MODE_PLAN_SESSION,
    ];

    public function __construct(private readonly BillingService $billing)
    {
    }

    /**
     * Punto único de entrada para cerrar una consulta.
     *
     * Toda la operación corre dentro de una transacción DB.
     *
     * @param  array<string, mixed>  $payload  ver contrato JSON
     */
    public function complete(Appointment $appointment, array $payload): array
    {
        $result = DB::transaction(function () use ($appointment, $payload) {
            $this->guardAppointmentIsInConsultation($appointment);
            $this->validatePayload($appointment, $payload);

            $mode = $payload['mode'];
            $user = Auth::user();
            $userId = $user?->id;

            $appointment->consultation_mode = $mode;
            $appointment->treatment_plan_id = $this->resolvePlanId($appointment, $payload);

            $evolution = $this->persistEvolution($appointment, $payload['evolution'], $userId);

            $this->persistOdontogramRecords($appointment, $payload['odontogram'] ?? [], $userId);

            $treatmentPlan = $this->upsertTreatmentPlan($appointment, $mode, $payload, $userId);
            if ($treatmentPlan) {
                $treatmentPlan->last_activity_at = now();
                $treatmentPlan->save();

                if ($treatmentPlan->wasRecentlyCreated || $appointment->treatment_plan_id === null) {
                    $appointment->treatment_plan_id = $treatmentPlan->id;
                }
            }

            $this->persistProcedureMaterials($appointment, $payload, $userId);

            $attachments = $this->persistAttachments(
                $appointment,
                $evolution,
                $payload['attachments'] ?? [],
                $userId,
            );

            $appointment->final_amount = $this->calculateFinalAmount($appointment, $treatmentPlan);
            $appointment->status = 'completed';
            $appointment->completed_at = now();
            $appointment->treatment_notes = $payload['evolution']['plan'] ?? $appointment->treatment_notes;
            $appointment->updated_by = $userId;
            $appointment->save();

            $this->scheduleFollowUpAppointment($appointment, $payload['next_appointment'] ?? null, $userId);

            $appointment->refresh()->load([
                'patient',
                'user',
                'appointmentType',
                'dentalChair',
                'treatmentPlan',
                'clinicalEvolutions',
                'procedureMaterials.product',
                'odontogramRecords.dentalPiece',
                'transactions',
                'quotations',
            ]);

            Log::info('Consultation completed', [
                'appointment_id' => $appointment->id,
                'mode' => $mode,
                'final_amount' => $appointment->final_amount,
                'treatment_plan_id' => $appointment->treatment_plan_id,
                'attachments' => count($attachments),
                'materials' => $appointment->procedureMaterials->count(),
            ]);

            $quotation = null;
            if ($this->billing->shouldAutoGenerateQuotation($appointment, $payload)) {
                $quotation = $this->billing->generateQuotationFromAppointment($appointment);
            }

            try {

                event(new AppointmentCompleted($appointment));

            } catch (\Throwable $e) {

                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);

            }
            return [
                'appointment' => $appointment,
                'quotation' => $quotation,
            ];
        });

        return $result;
    }

    /**
     * Marca la cita como en consulta (in_consultation) si aún no lo está.
     * Devuelve la cita refrescada.
     */
    public function checkIn(Appointment $appointment): Appointment
    {
        if ($appointment->status === 'in_consultation') {
            return $appointment;
        }

        $allowedFrom = ['scheduled', 'confirmed'];
        if (!in_array($appointment->status, $allowedFrom, true)) {
            throw AppointmentNotInConsultationException::make($appointment->status);
        }

        $appointment->status = 'in_consultation';
        $appointment->checked_in_at = now();
        $appointment->updated_by = Auth::id();
        $appointment->save();

        try {

            event(new \App\Events\AppointmentCheckedIn($appointment));

        } catch (\Throwable $e) {

            Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);

        }
        return $appointment->refresh();
    }

    private function guardAppointmentIsInConsultation(Appointment $appointment): void
    {
        if ($appointment->status !== 'in_consultation') {
            throw AppointmentNotInConsultationException::make($appointment->status);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(Appointment $appointment, array $payload): void
    {
        $mode = $payload['mode'] ?? null;
        if (!in_array($mode, self::VALID_MODES, true)) {
            throw InvalidConsultationModeException::make($mode);
        }

        $evolution = $payload['evolution'] ?? null;
        if (!is_array($evolution)) {
            throw new MissingEvolutionException();
        }

        $requiredSoap = ['subjective', 'objective', 'assessment', 'plan'];
        $missing = array_filter($requiredSoap, fn ($k) => trim((string) ($evolution[$k] ?? '')) === '');
        if (!empty($missing)) {
            throw new MissingEvolutionException();
        }

        $skipMaterials = (bool) ($payload['skip_materials'] ?? false);
        $materials = $payload['materials'] ?? [];
        $materialsCount = is_array($materials) ? count($materials) : 0;

        if ($mode === self::MODE_CONSULTATION && $materialsCount > 0) {
            throw new UnexpectedMaterialsException();
        }

        $requiresMaterials = (bool) ($appointment->appointmentType?->requires_materials ?? false);
        if ($requiresMaterials && $materialsCount === 0 && !$skipMaterials) {
            throw new MissingMaterialsException(
                'el tipo de cita requiere registrar materiales o confirmar "skip_materials"'
            );
        }

        if ($mode === self::MODE_PLAN_SESSION) {
            $planData = $payload['treatment_plan'] ?? null;
            $planId = is_array($planData) ? ($planData['id'] ?? null) : null;
            if (!$planId) {
                throw InvalidTreatmentPlanException::requiresPlanId();
            }

            $plan = TreatmentPlan::find($planId);
            if (!$plan || $plan->patient_id !== $appointment->patient_id) {
                throw InvalidTreatmentPlanException::notFound($planId);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $evolution
     */
    private function persistEvolution(Appointment $appointment, array $evolution, ?int $userId): ClinicalEvolution
    {
        $record = MedicalRecord::firstOrCreate(
            ['patient_id' => $appointment->patient_id, 'is_active' => true],
            [
                'created_by' => $userId,
                'record_number' => 'HC-' . date('Y') . '-' . strtoupper(Str::random(6)),
                'first_visit_date' => now()->toDateString(),
            ],
        );

        return ClinicalEvolution::create([
            'patient_id' => $appointment->patient_id,
            'medical_record_id' => $record->id,
            'appointment_id' => $appointment->id,
            'created_by' => $userId,
            'evolution_date' => $evolution['evolution_date'] ?? now()->toDateString(),
            'specialty' => $evolution['specialty'] ?? Auth::user()?->specialty,
            'subjective' => $evolution['subjective'] ?? null,
            'objective' => $evolution['objective'] ?? null,
            'assessment' => $evolution['assessment'] ?? null,
            'plan' => $evolution['plan'] ?? null,
            'procedures_performed' => $evolution['procedures_performed'] ?? null,
            'materials_used' => $evolution['materials_used'] ?? null,
            'prescriptions' => $evolution['prescriptions'] ?? null,
            'recommendations' => $evolution['recommendations'] ?? null,
            'next_appointment_notes' => $evolution['next_appointment_notes'] ?? null,
            'vital_signs' => $evolution['vital_signs'] ?? null,
            'clinical_measurements' => $evolution['clinical_measurements'] ?? null,
            'requires_follow_up' => (bool) ($evolution['requires_follow_up'] ?? false),
            'follow_up_date' => $evolution['follow_up_date'] ?? null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function persistOdontogramRecords(Appointment $appointment, array $records, ?int $userId): void
    {
        if (empty($records)) {
            return;
        }

        $odontogram = Odontogram::firstOrCreate(
            ['patient_id' => $appointment->patient_id, 'is_active' => true],
            ['created_by' => $userId],
        );

        foreach ($records as $r) {
            OdontogramRecord::create([
                'odontogram_id' => $odontogram->id,
                'dental_piece_id' => $r['dental_piece_id'] ?? null,
                'tooth_surface_id' => $r['tooth_surface_id'] ?? null,
                'condition_code' => $r['condition'] ?? $r['condition_code'] ?? null,
                'condition_name' => $r['condition_name'] ?? ($r['condition'] ?? null),
                'diagnosis' => $r['diagnosis'] ?? null,
                'treatment_notes' => $r['notes'] ?? null,
                'surfaces' => $r['surfaces'] ?? null,
                'color' => $r['color'] ?? null,
                'appointment_id' => $appointment->id,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TreatmentPlan|null
     */
    private function upsertTreatmentPlan(Appointment $appointment, string $mode, array $payload, ?int $userId): ?TreatmentPlan
    {
        $planData = $payload['treatment_plan'] ?? null;

        if ($mode === self::MODE_PLAN_SESSION) {
            $plan = TreatmentPlan::with('items')->findOrFail($planData['id']);

            $itemUpdates = $planData['items'] ?? [];
            foreach ($itemUpdates as $update) {
                $item = $plan->items->firstWhere('id', $update['id'] ?? null);
                if (!$item) {
                    continue;
                }
                $item->status = $update['status'] ?? $item->status;
                $item->save();
            }

            $this->recalculatePlanTotals($plan);

            return $plan;
        }

        if ($mode === self::MODE_EXECUTION) {
            $items = $planData['items'] ?? [];
            $asProposed = (bool) ($planData['as_proposed'] ?? false);
            $itemStatus = $asProposed ? 'proposed' : 'completed';

            $plan = TreatmentPlan::create([
                'patient_id' => $appointment->patient_id,
                'origin_appointment_id' => $appointment->id,
                'created_by' => $userId,
                'plan_number' => $this->generatePlanNumber(),
                'title' => $planData['title'] ?? $appointment->appointmentType?->name ?? 'Procedimiento',
                'description' => $planData['description'] ?? null,
                'status' => $asProposed ? 'proposed' : 'completed',
                'start_date' => now()->toDateString(),
                'end_date' => $asProposed ? null : now()->toDateString(),
                'notes' => $planData['notes'] ?? null,
            ]);

            foreach ($items as $i => $item) {
                TreatmentPlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'procedure_name' => $item['procedure_name'] ?? 'Procedimiento',
                    'procedure_description' => $item['procedure_description'] ?? null,
                    'dental_piece_id' => $item['dental_piece_id'] ?? null,
                    'specialty' => $item['specialty'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'total_cost' => ($item['unit_cost'] ?? 0) * ($item['quantity'] ?? 1),
                    'estimated_duration_minutes' => $item['estimated_duration_minutes'] ?? null,
                    'phase_number' => $item['phase_number'] ?? 1,
                    'status' => $itemStatus,
                    'materials_required' => $item['materials_required'] ?? null,
                    'requires_anesthesia' => $item['requires_anesthesia'] ?? false,
                    'is_optional' => $item['is_optional'] ?? false,
                ]);
            }

            $this->recalculatePlanTotals($plan);

            return $plan;
        }

        if ($mode === self::MODE_CONSULTATION && ($planData['as_proposed'] ?? false)) {
            $items = $planData['items'] ?? [];
            $plan = TreatmentPlan::create([
                'patient_id' => $appointment->patient_id,
                'origin_appointment_id' => $appointment->id,
                'created_by' => $userId,
                'plan_number' => $this->generatePlanNumber(),
                'title' => $planData['title'] ?? 'Propuesta de tratamiento',
                'description' => $planData['description'] ?? null,
                'status' => 'proposed',
                'start_date' => now()->toDateString(),
                'notes' => $planData['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                TreatmentPlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'procedure_name' => $item['procedure_name'] ?? 'Procedimiento',
                    'dental_piece_id' => $item['dental_piece_id'] ?? null,
                    'specialty' => $item['specialty'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'total_cost' => ($item['unit_cost'] ?? 0) * ($item['quantity'] ?? 1),
                    'phase_number' => $item['phase_number'] ?? 1,
                    'status' => 'proposed',
                    'materials_required' => $item['materials_required'] ?? null,
                ]);
            }

            $this->recalculatePlanTotals($plan);

            return $plan;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistProcedureMaterials(Appointment $appointment, array $payload, ?int $userId): void
    {
        $materials = $payload['materials'] ?? [];
        if (empty($materials) || !is_array($materials)) {
            return;
        }

        $planItemIds = $this->resolvePlanItemIds($appointment);

        foreach ($materials as $m) {
            $productId = $m['product_id'] ?? null;
            if (!$productId) {
                continue;
            }

            $unitCost = $m['unit_cost'] ?? (float) (Product::find($productId)?->cost ?? 0);
            $qty = (float) ($m['quantity_used'] ?? 1);

            ProcedureMaterial::create([
                'appointment_id' => $appointment->id,
                'treatment_plan_item_id' => $this->guessPlanItemId(
                    $m,
                    $planItemIds,
                ),
                'product_id' => $productId,
                'created_by' => $userId,
                'quantity_used' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => round($qty * (float) $unitCost, 2),
                'batch_number' => $m['batch_number'] ?? null,
                'expiry_date' => $m['expiry_date'] ?? null,
                'notes' => $m['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<int, ClinicalAttachment>
     */
    private function persistAttachments(
        Appointment $appointment,
        ClinicalEvolution $evolution,
        array $attachments,
        ?int $userId,
    ): array {
        $created = [];
        foreach ($attachments as $att) {
            $file = $att['file'] ?? null;
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('clinical-attachments', $fileName, 'public');

            $created[] = ClinicalAttachment::create([
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'clinical_evolution_id' => $evolution->id,
                'created_by' => $userId,
                'file_name' => $fileName,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $this->detectFileType($file->getMimeType()),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'category' => $att['category'] ?? 'general',
                'description' => $att['description'] ?? null,
                'is_private' => (bool) ($att['is_private'] ?? false),
            ]);
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>|null  $next
     */
    private function scheduleFollowUpAppointment(Appointment $appointment, ?array $next, ?int $userId): void
    {
        if (empty($next) || empty($next['scheduled_at'])) {
            return;
        }

        Appointment::create([
            'patient_id' => $appointment->patient_id,
            'user_id' => $appointment->user_id,
            'dental_chair_id' => $appointment->dental_chair_id,
            'appointment_type_id' => $next['appointment_type_id'] ?? $appointment->appointment_type_id,
            'scheduled_at' => Carbon::parse($next['scheduled_at']),
            'duration_minutes' => $next['duration_minutes'] ?? 30,
            'status' => 'scheduled',
            'notes' => $next['notes'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function resolvePlanId(Appointment $appointment, array $payload): ?int
    {
        $planData = $payload['treatment_plan'] ?? null;
        if (is_array($planData) && !empty($planData['id'])) {
            return (int) $planData['id'];
        }

        return null;
    }

    private function resolvePlanItemIds(Appointment $appointment): array
    {
        $planId = $appointment->treatment_plan_id;
        if (!$planId) {
            return [];
        }

        return TreatmentPlanItem::where('treatment_plan_id', $planId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function guessPlanItemId(array $material, array $planItemIds): ?int
    {
        if (empty($planItemIds)) {
            return null;
        }

        if (!empty($material['treatment_plan_item_id'])) {
            $candidate = (int) $material['treatment_plan_item_id'];
            if (in_array($candidate, $planItemIds, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function calculateFinalAmount(Appointment $appointment, ?TreatmentPlan $plan): float
    {
        if ($plan) {
            $executed = $plan->items()->where('status', 'completed')->get();
            if ($executed->isNotEmpty()) {
                return (float) $executed->sum(fn ($i) => (float) $i->total_cost);
            }
        }

        return (float) ($appointment->appointmentType?->price ?? 0);
    }

    private function recalculatePlanTotals(TreatmentPlan $plan): void
    {
        $subtotal = (float) $plan->items()->sum('total_cost');
        $discount = (float) ($plan->discount_amount ?? 0);
        $plan->total_cost = $subtotal;
        $plan->final_cost = max(0, $subtotal - $discount);
        $plan->save();
    }

    private function generatePlanNumber(): string
    {
        do {
            $number = 'TP-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (TreatmentPlan::where('plan_number', $number)->exists());

        return $number;
    }

    private function detectFileType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (in_array($mime, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true)) {
            return 'document';
        }
        return 'other';
    }
}
