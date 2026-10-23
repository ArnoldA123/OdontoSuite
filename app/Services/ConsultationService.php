<?php

namespace App\Services;

use App\Events\AppointmentCompleted;
use App\Exceptions\Consultation\AppointmentNotInConsultationException;
use App\Exceptions\Consultation\InvalidConsultationModeException;
use App\Exceptions\Consultation\InvalidTreatmentPlanException;
use App\Exceptions\Consultation\MissingClosedEncounterException;
use App\Exceptions\Consultation\MissingEvolutionException;
use App\Exceptions\Consultation\MissingMaterialsException;
use App\Exceptions\Consultation\NextAppointmentRequiresAppointmentException;
use App\Exceptions\Consultation\UnexpectedMaterialsException;
use App\Exceptions\Encounter\EncounterException;
use App\Models\Appointment;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\Odontogram;
use App\Models\OdontogramRecord;
use App\Models\ProcedureCatalog;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * F2 (#282) — the single closure of the clinical act.
 *
 * `close()` is the only implementation: it closes one encounter and produces
 * everything the act generated (evolution, findings, procedures, materials,
 * attachments, amount and quotation) from it. `complete()` is the appointment
 * adapter that keeps the wizard's contract: it resolves or opens the act of the
 * appointment and delegates.
 *
 * The appointment is a projection: `status`, `completed_at`, `final_amount`
 * and `consultation_mode` are written here, after the encounter is closed in
 * the same transaction, and nowhere else.
 */
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

    public function __construct(
        private readonly BillingService $billing,
        private readonly ClinicalEncounterService $encounters,
        private readonly InventoryService $inventory,
        private readonly TreatmentPlanSessionService $sessions,
        private readonly ContinuityService $continuity,
        private readonly RecallService $recalls,
        private readonly PatientChargeService $charges,
    ) {}

    /**
     * Appointment door (`POST /api/appointments/{id}/complete`).
     *
     * @param  array<string, mixed>                                                            $payload ver contrato JSON
     * @return array{appointment: Appointment, encounter: ClinicalEncounter, quotation: mixed}
     */
    public function complete(Appointment $appointment, array $payload): array
    {
        return DB::transaction(function () use ($appointment, $payload) {
            $current = $this->lockAppointment($appointment);
            $this->guardAppointmentIsInConsultation($current);

            $encounter = $this->encounters->openForAppointment($current, Auth::user());

            return $this->close($encounter, $payload);
        });
    }

    /**
     * The single closure. `POST /api/encounters/{id}/complete` calls it
     * directly; the appointment door reaches it through `complete()`.
     *
     * @param  array<string, mixed>                                                             $payload
     * @return array{appointment: ?Appointment, encounter: ClinicalEncounter, quotation: mixed}
     */
    public function close(ClinicalEncounter $encounter, array $payload): array
    {
        return DB::transaction(function () use ($encounter, $payload) {
            $current = $this->lockEncounter($encounter);
            $this->guardEncounterIsOpen($current);

            $appointment = $current->appointment;
            $mode = $this->resolveMode($payload);
            $this->validatePayload($current, $appointment, $mode, $payload);

            $user = Auth::user();
            $userId = $user?->id;

            $record = $this->medicalRecordFor($current->patient_id, $userId);
            $evolution = $this->persistEvolution($current, $record, $payload['evolution'], $userId);

            $this->persistOdontogramRecords($current, $payload['odontogram'] ?? [], $userId);

            $planId = $this->resolvePlanId($payload);
            $treatmentPlan = $this->upsertTreatmentPlan($current, $appointment, $mode, $payload, $userId);
            if ($treatmentPlan) {
                $treatmentPlan->last_activity_at = now();
                $treatmentPlan->save();

                if ($treatmentPlan->wasRecentlyCreated || $planId === null) {
                    $planId = $treatmentPlan->id;
                }
            }

            $this->persistProcedureMaterials($current, $planId, $payload, $userId);

            $attachments = $this->persistAttachments(
                $current,
                $evolution,
                $payload['attachments'] ?? [],
                $userId,
            );

            $finalAmount = $this->calculateFinalAmount($appointment, $treatmentPlan);

            $current->forceFill([
                'mode' => $mode,
                'medical_record_id' => $record->id,
                'treatment_plan_id' => $planId,
                'final_amount' => $finalAmount,
                'status' => ClinicalEncounter::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $userId,
            ])->save();

            // #287 (T2, S3/S4) — el acto escribe su cargo en la misma
            // transacción: el libro del paciente y el registro clínico no
            // pueden discrepar del importe. La escritura es idempotente por
            // `clinical_encounter_id`.
            $this->charges->chargeForEncounter($current);

            if ($appointment !== null) {
                $this->projectOnAppointment($appointment, $current, $mode, $finalAmount, $payload, $userId);
                $this->persistContinuity($current, $appointment, $payload, $userId);
            }

            $this->persistRecall($current, $appointment, $payload, $userId);

            $current->refresh()->load([
                'patient',
                'user',
                'appointment',
                'medicalRecord',
                'treatmentPlan',
                'clinicalEvolutions',
                'procedureMaterials.product',
                'odontogramRecords.dentalPiece',
                'attachments',
                'quotations',
            ]);

            Log::info('Clinical encounter closed', [
                'encounter_id' => $current->id,
                'appointment_id' => $current->appointment_id,
                'mode' => $mode,
                'final_amount' => $current->final_amount,
                'treatment_plan_id' => $current->treatment_plan_id,
                'attachments' => count($attachments),
                'materials' => $current->procedureMaterials->count(),
            ]);

            $quotation = null;
            if ($this->billing->shouldAutoGenerateQuotation($current, $mode, $payload)) {
                $quotation = $this->billing->generateQuotationFromEncounter($current);
            }

            if ($appointment !== null) {
                $this->dispatchCompleted($appointment->refresh()->load([
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
                ]));
            }

            return [
                'appointment' => $appointment?->refresh(),
                'encounter' => $current,
                'quotation' => $quotation,
            ];
        });
    }

    /**
     * F1 (#281): the closure only accepts an appointment the professional
     * already started; the arrival lives in `arrived` and is not enough.
     */
    private function guardAppointmentIsInConsultation(Appointment $appointment): void
    {
        if ($appointment->status !== 'in_consultation') {
            throw AppointmentNotInConsultationException::make($appointment->status);
        }
    }

    /**
     * An act is closed exactly once. A second call finds a `closed` encounter
     * and is refused instead of documenting the same act twice.
     */
    private function guardEncounterIsOpen(ClinicalEncounter $encounter): void
    {
        if (! $encounter->isOpen()) {
            throw EncounterException::alreadyClosed($encounter->status);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveMode(array $payload): string
    {
        $mode = $payload['mode'] ?? null;
        if (! in_array($mode, self::VALID_MODES, true)) {
            throw InvalidConsultationModeException::make($mode);
        }

        return $mode;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePayload(
        ClinicalEncounter $encounter,
        ?Appointment $appointment,
        string $mode,
        array $payload,
    ): void {
        $evolution = $payload['evolution'] ?? null;
        if (! is_array($evolution)) {
            throw new MissingEvolutionException;
        }

        $requiredSoap = ['subjective', 'objective', 'assessment', 'plan'];
        $missing = array_filter($requiredSoap, fn ($k) => trim((string) ($evolution[$k] ?? '')) === '');
        if (! empty($missing)) {
            throw new MissingEvolutionException;
        }

        $skipMaterials = (bool) ($payload['skip_materials'] ?? false);
        $materials = $payload['materials'] ?? [];
        $materialsCount = is_array($materials) ? count($materials) : 0;

        if ($mode === self::MODE_CONSULTATION && $materialsCount > 0) {
            throw new UnexpectedMaterialsException;
        }

        // #295 (S7): la exigencia sale del recetario de los procedimientos que
        // el acto documenta, no del flag manual del tipo de cita.
        if ($materialsCount === 0 && ! $skipMaterials && $this->requiresMaterials($encounter, $appointment, $payload)) {
            throw new MissingMaterialsException(
                'el tipo de cita requiere registrar materiales o confirmar "skip_materials"'
            );
        }

        if ($mode === self::MODE_PLAN_SESSION) {
            $planData = $payload['treatment_plan'] ?? null;
            $planId = is_array($planData) ? ($planData['id'] ?? null) : null;
            if (! $planId) {
                throw InvalidTreatmentPlanException::requiresPlanId();
            }

            $plan = TreatmentPlan::find($planId);
            if (! $plan || $plan->patient_id !== $encounter->patient_id) {
                throw InvalidTreatmentPlanException::notFound($planId);
            }
        }

        // Continuity for an act without an appointment belongs to F4 (#284):
        // `appointments` requires a chair and a type that an urgency act has
        // no way to know here.
        if ($appointment === null && ! empty($payload['next_appointment'])) {
            throw NextAppointmentRequiresAppointmentException::make();
        }
    }

    /**
     * #295 (S7): un acto exige materiales cuando alguno de los procedimientos
     * que documenta declara recetario. Una sola consulta resuelve la receta de
     * los ids implicados; un acto sin procedimientos no exige nada.
     *
     * @param array<string, mixed> $payload
     */
    private function requiresMaterials(ClinicalEncounter $encounter, ?Appointment $appointment, array $payload): bool
    {
        $procedureIds = $this->implicatedProcedureIds($encounter, $appointment, $payload);

        if ($procedureIds === []) {
            return false;
        }

        return ProcedureCatalog::query()
            ->whereIn('id', $procedureIds)
            ->whereHas('materialRecipe')
            ->exists();
    }

    /**
     * Procedimientos que el acto documenta: el del tipo de cita, los ítems que
     * trae el payload y los del plan que el acto ya tiene guardado.
     *
     * @param  array<string, mixed> $payload
     * @return list<int>
     */
    private function implicatedProcedureIds(
        ClinicalEncounter $encounter,
        ?Appointment $appointment,
        array $payload,
    ): array {
        $ids = [];

        $defaultProcedureId = $appointment?->appointmentType?->default_procedure_catalog_id;
        if ($defaultProcedureId !== null) {
            $ids[] = (int) $defaultProcedureId;
        }

        $planData = $payload['treatment_plan'] ?? null;
        if (is_array($planData)) {
            foreach ($planData['items'] ?? [] as $item) {
                if (is_array($item) && ! empty($item['procedure_catalog_id'])) {
                    $ids[] = (int) $item['procedure_catalog_id'];
                }
            }
        }

        $planIds = array_values(array_filter([
            $encounter->treatment_plan_id,
            is_array($planData) ? ($planData['id'] ?? null) : null,
        ]));

        if ($planIds !== []) {
            $stored = TreatmentPlanItem::query()
                ->whereIn('treatment_plan_id', array_map('intval', $planIds))
                ->whereNotNull('procedure_catalog_id')
                ->pluck('procedure_catalog_id');

            foreach ($stored as $procedureId) {
                $ids[] = (int) $procedureId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function medicalRecordFor(int $patientId, ?int $userId): MedicalRecord
    {
        return MedicalRecord::firstOrCreate(
            ['patient_id' => $patientId, 'is_active' => true],
            [
                'created_by' => $userId,
                'record_number' => 'HC-'.date('Y').'-'.strtoupper(Str::random(6)),
                'first_visit_date' => now()->toDateString(),
            ],
        );
    }

    /**
     * @param array<string, mixed> $evolution
     */
    private function persistEvolution(
        ClinicalEncounter $encounter,
        MedicalRecord $record,
        array $evolution,
        ?int $userId,
    ): ClinicalEvolution {
        return ClinicalEvolution::create([
            'patient_id' => $encounter->patient_id,
            'appointment_id' => $encounter->appointment_id,
            'clinical_encounter_id' => $encounter->id,
            'medical_record_id' => $record->id,
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
     * @param array<int, array<string, mixed>> $records
     */
    private function persistOdontogramRecords(ClinicalEncounter $encounter, array $records, ?int $userId): void
    {
        if (empty($records)) {
            return;
        }

        $odontogram = Odontogram::firstOrCreate(
            ['patient_id' => $encounter->patient_id, 'is_active' => true],
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
                'appointment_id' => $encounter->appointment_id,
                'clinical_encounter_id' => $encounter->id,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertTreatmentPlan(
        ClinicalEncounter $encounter,
        ?Appointment $appointment,
        string $mode,
        array $payload,
        ?int $userId,
    ): ?TreatmentPlan {
        $planData = $payload['treatment_plan'] ?? null;

        if ($mode === self::MODE_PLAN_SESSION) {
            $plan = TreatmentPlan::with('items')->findOrFail($planData['id']);

            // F4 (#284): the session consumes plan lines. The status of the item
            // is derived from those consumptions, never written here.
            $this->sessions->consume(
                $encounter,
                $plan,
                $planData['items'] ?? [],
                $userId,
            );

            $this->recalculatePlanTotals($plan);

            return $plan;
        }

        if ($mode === self::MODE_EXECUTION) {
            $items = $planData['items'] ?? [];
            $asProposed = (bool) ($planData['as_proposed'] ?? false);
            $itemStatus = $asProposed ? 'proposed' : 'pending';

            $plan = TreatmentPlan::create([
                'patient_id' => $encounter->patient_id,
                'origin_appointment_id' => $encounter->appointment_id,
                'created_by' => $userId,
                'plan_number' => $this->generatePlanNumber(),
                'title' => $planData['title'] ?? $appointment?->appointmentType?->name ?? 'Procedimiento',
                'description' => $planData['description'] ?? null,
                'status' => $asProposed ? 'proposed' : 'completed',
                'start_date' => now()->toDateString(),
                'end_date' => $asProposed ? null : now()->toDateString(),
                'notes' => $planData['notes'] ?? null,
            ]);

            $lines = [];
            foreach ($items as $item) {
                $line = TreatmentPlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'procedure_catalog_id' => $item['procedure_catalog_id'] ?? null,
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

                if (! $asProposed) {
                    $lines[] = ['id' => $line->id, 'consumed_quantity' => $line->quantity];
                }
            }

            if ($lines !== []) {
                $this->sessions->consume($encounter, $plan->refresh()->load('items'), $lines, $userId);
            }

            $this->recalculatePlanTotals($plan);

            return $plan;
        }

        if ($mode === self::MODE_CONSULTATION && ($planData['as_proposed'] ?? false)) {
            $items = $planData['items'] ?? [];
            $plan = TreatmentPlan::create([
                'patient_id' => $encounter->patient_id,
                'origin_appointment_id' => $encounter->appointment_id,
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
                    'procedure_catalog_id' => $item['procedure_catalog_id'] ?? null,
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
     * El consumo —descuento de stock, guarda y salida por lote— vive en el
     * servicio de almacén (#295 S1/S2/S5): una sola implementación para el acto.
     *
     * @param array<string, mixed> $payload
     */
    private function persistProcedureMaterials(
        ClinicalEncounter $encounter,
        ?int $planId,
        array $payload,
        ?int $userId,
    ): void {
        $materials = $payload['materials'] ?? [];
        if (empty($materials) || ! is_array($materials)) {
            return;
        }

        // El acto conoce su plan antes de persistirse y el cierre lo escribe
        // después; adelantarlo en memoria permite que el consumo enlace cada
        // línea con el ítem del plan que documenta el acto.
        $encounter->treatment_plan_id = $planId;

        $this->inventory->consumeForEncounter(
            $encounter,
            array_values($materials),
            $userId,
            Auth::user()?->branch_id,
        );
    }

    /**
     * @param  array<int, array<string, mixed>> $attachments
     * @return array<int, ClinicalAttachment>
     */
    private function persistAttachments(
        ClinicalEncounter $encounter,
        ClinicalEvolution $evolution,
        array $attachments,
        ?int $userId,
    ): array {
        $created = [];
        foreach ($attachments as $att) {
            $file = $att['file'] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('clinical-attachments', $fileName, 'public');

            $created[] = ClinicalAttachment::create([
                'patient_id' => $encounter->patient_id,
                'appointment_id' => $encounter->appointment_id,
                'clinical_encounter_id' => $encounter->id,
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
     * The appointment is the projection of the act, and this is its only pen
     * (S4): status, close time, amount and mode come from the encounter that
     * was just closed in this transaction.
     *
     * @param array<string, mixed> $payload
     */
    private function projectOnAppointment(
        Appointment $appointment,
        ClinicalEncounter $encounter,
        string $mode,
        float $finalAmount,
        array $payload,
        ?int $userId,
    ): void {
        // S7: an appointment is `completed` only with a closed act. The check
        // reads the persisted state, not the in-memory model, so it fails if
        // the closure order is ever broken.
        if (! $appointment->clinicalEncounters()->where('status', ClinicalEncounter::STATUS_CLOSED)->exists()) {
            throw MissingClosedEncounterException::make();
        }

        $appointment->forceFill([
            'consultation_mode' => $mode,
            'treatment_plan_id' => $encounter->treatment_plan_id,
            'final_amount' => $finalAmount,
            'status' => 'completed',
            'completed_at' => now(),
            'treatment_notes' => $payload['evolution']['plan'] ?? $appointment->treatment_notes,
            'updated_by' => $userId,
        ])->save();
    }

    /**
     * F4 (#284) — the professional leaves the conduct; reception books it.
     * The closure no longer writes an appointment row directly.
     *
     * @param array<string, mixed> $payload
     */
    private function persistContinuity(
        ClinicalEncounter $encounter,
        Appointment $appointment,
        array $payload,
        ?int $userId,
    ): void {
        $this->continuity->requestFromClosure(
            $encounter,
            $appointment,
            $payload['next_appointment'] ?? null,
            $userId,
        );
    }

    /**
     * F4 (#284) — the recall interval belongs to the patient, with its reason
     * and its due date. It is not a reminder template.
     *
     * @param array<string, mixed> $payload
     */
    private function persistRecall(
        ClinicalEncounter $encounter,
        ?Appointment $appointment,
        array $payload,
        ?int $userId,
    ): void {
        $recall = $payload['recall'] ?? null;
        $user = Auth::user();

        if (! is_array($recall) || ! $user instanceof \App\Models\User) {
            return;
        }

        $this->recalls->create([
            'patient_id' => $encounter->patient_id,
            'branch_id' => $appointment?->branch_id ?? $encounter->patient?->branch_id,
            'interval_months' => (int) $recall['interval_months'],
            'reason' => $recall['reason'],
            'due_date' => $recall['due_date'] ?? null,
            'notes' => $recall['notes'] ?? null,
        ], $user);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolvePlanId(array $payload): ?int
    {
        $planData = $payload['treatment_plan'] ?? null;
        if (is_array($planData) && ! empty($planData['id'])) {
            return (int) $planData['id'];
        }

        return null;
    }

    private function calculateFinalAmount(?Appointment $appointment, ?TreatmentPlan $plan): float
    {
        if ($plan) {
            $executed = $plan->items()->where('status', 'completed')->get();
            if ($executed->isNotEmpty()) {
                return (float) $executed->sum(fn ($i) => (float) $i->total_cost);
            }
        }

        return (float) ($appointment?->appointmentType?->price ?? 0);
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
            $number = 'TP-'.date('Y').'-'.strtoupper(Str::random(6));
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

    /**
     * The closure owns the transition, so the row is re-read under a lock; a
     * second call must see `closed` and be refused.
     */
    private function lockEncounter(ClinicalEncounter $encounter): ClinicalEncounter
    {
        return ClinicalEncounter::query()
            ->whereKey($encounter->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockAppointment(Appointment $appointment): Appointment
    {
        return Appointment::query()
            ->whereKey($appointment->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * AGENTS.md §7: a failing broadcast must never break the clinical record.
     */
    private function dispatchCompleted(Appointment $appointment): void
    {
        try {
            event(new AppointmentCompleted($appointment));
        } catch (\Throwable $e) {
            Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);
        }
    }
}
