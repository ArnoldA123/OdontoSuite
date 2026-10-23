<?php

namespace App\Services;

use App\Models\ProcedureCatalog;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentPlanItemAlternative;
use App\Support\ProcedureSiteRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #288 — option A/B per line, and the record of what was declined.
 *
 * Accepting an option copies it into the line (that is the treatment the patient
 * chose) and rejects the other options with their date and reason. Rejecting an
 * optional line keeps the line itself as the evidence of the refusal.
 */
class TreatmentPlanAlternativeService
{
    public function __construct(private readonly TreatmentPlanVersionService $versions) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(TreatmentPlanItem $item, array $data, ?int $userId): TreatmentPlanItemAlternative
    {
        $optionCode = strtoupper((string) ($data['option_code'] ?? $this->nextOptionCode($item)));

        if (TreatmentPlanItemAlternative::where('treatment_plan_item_id', $item->id)
            ->where('option_code', $optionCode)
            ->exists()) {
            throw ValidationException::withMessages([
                'option_code' => ["La opción {$optionCode} ya existe para este ítem."],
            ]);
        }

        $payload = $this->preparePayload($item, $data);
        $payload['treatment_plan_item_id'] = $item->id;
        $payload['option_code'] = $optionCode;
        $payload['status'] = TreatmentPlanItemAlternative::STATUS_PROPOSED;
        $payload['created_by'] = $userId;

        $alternative = TreatmentPlanItemAlternative::create($payload);

        $this->versions->record($item->treatmentPlan()->firstOrFail(), 'Alternativa agregada', $userId);

        return $alternative;
    }

    /**
     * @return array{alternative: TreatmentPlanItemAlternative, item: TreatmentPlanItem}
     */
    public function accept(TreatmentPlanItemAlternative $alternative, ?int $userId): array
    {
        return DB::transaction(function () use ($alternative, $userId) {
            $current = TreatmentPlanItemAlternative::query()
                ->whereKey($alternative->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $item = $current->treatmentPlanItem()->lockForUpdate()->firstOrFail();

            if ($item->consumptions()->exists()) {
                throw ValidationException::withMessages([
                    'alternatives' => ['El ítem ya fue ejecutado; la alternativa no puede reemplazarlo.'],
                ]);
            }

            if ($current->status !== TreatmentPlanItemAlternative::STATUS_PROPOSED) {
                throw ValidationException::withMessages([
                    'alternatives' => ["Solo una alternativa propuesta se puede aceptar (estado actual: {$current->status})."],
                ]);
            }

            $item->fill([
                'procedure_catalog_id' => $current->procedure_catalog_id,
                'procedure_name' => $current->procedure_name,
                'procedure_description' => $current->procedure_description,
                'dental_piece_id' => $current->dental_piece_id,
                'tooth_surface_id' => $current->tooth_surface_id,
                'site_code' => $current->site_code,
                'quantity' => $current->quantity,
                'unit_cost' => $current->unit_cost,
                'total_cost' => $current->total_cost,
            ])->save();

            $current->update([
                'status' => TreatmentPlanItemAlternative::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            TreatmentPlanItemAlternative::where('treatment_plan_item_id', $item->id)
                ->where('id', '!=', $current->id)
                ->where('status', TreatmentPlanItemAlternative::STATUS_PROPOSED)
                ->update([
                    'status' => TreatmentPlanItemAlternative::STATUS_REJECTED,
                    'rejected_at' => now(),
                    'rejection_reason' => "Opción no elegida al aceptar la opción {$current->option_code}.",
                    'updated_at' => now(),
                ]);

            $this->versions->record($item->treatmentPlan()->firstOrFail(), 'Alternativa aceptada', $userId);

            return [
                'alternative' => $current->refresh(),
                'item' => $item->refresh(),
            ];
        });
    }

    public function reject(TreatmentPlanItemAlternative $alternative, string $reason, ?int $userId = null): TreatmentPlanItemAlternative
    {
        if ($alternative->status !== TreatmentPlanItemAlternative::STATUS_PROPOSED) {
            throw ValidationException::withMessages([
                'alternatives' => ["Solo una alternativa propuesta se puede rechazar (estado actual: {$alternative->status})."],
            ]);
        }

        $alternative->update([
            'status' => TreatmentPlanItemAlternative::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->versions->record($alternative->treatmentPlanItem()->firstOrFail()->treatmentPlan()->firstOrFail(), 'Alternativa rechazada', $userId);

        return $alternative->refresh();
    }

    public function rejectItem(TreatmentPlanItem $item, string $reason, ?int $userId = null): TreatmentPlanItem
    {
        if (! $item->is_optional && $item->priority !== 'optional') {
            throw ValidationException::withMessages([
                'items' => ['Solo un ítem opcional puede rechazarse; los ítems urgentes o electivos se cancelan con la atención.'],
            ]);
        }

        if ($item->consumptions()->exists()) {
            throw ValidationException::withMessages([
                'items' => ['El ítem ya fue ejecutado y no puede rechazarse.'],
            ]);
        }

        $item->update([
            'status' => 'cancelled',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->versions->record($item->treatmentPlan()->firstOrFail(), 'Ítem opcional rechazado', $userId);

        return $item->refresh();
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function preparePayload(TreatmentPlanItem $item, array $data): array
    {
        $quantity = (int) ($data['quantity'] ?? 1);
        $unitCost = (float) ($data['unit_cost'] ?? $item->unit_cost);

        $payload = [
            'procedure_catalog_id' => $data['procedure_catalog_id'] ?? $item->procedure_catalog_id,
            'procedure_name' => $data['procedure_name'] ?? $item->procedure_name,
            'procedure_description' => $data['procedure_description'] ?? $item->procedure_description,
            'dental_piece_id' => $data['dental_piece_id'] ?? $item->dental_piece_id,
            'tooth_surface_id' => $data['tooth_surface_id'] ?? $item->tooth_surface_id,
            'site_code' => $data['site_code'] ?? $item->site_code,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
            'notes' => $data['notes'] ?? null,
        ];

        if (! empty($payload['procedure_catalog_id'])) {
            $procedure = ProcedureCatalog::find($payload['procedure_catalog_id']);
            $site = ProcedureSiteRule::normalize([
                'dental_piece_id' => $payload['dental_piece_id'],
                'tooth_surface_id' => $payload['tooth_surface_id'],
                'site_code' => $payload['site_code'],
            ]);

            ProcedureSiteRule::assertFor($procedure, $site);

            $payload = array_merge($payload, $site);
        }

        return $payload;
    }

    private function nextOptionCode(TreatmentPlanItem $item): string
    {
        $used = TreatmentPlanItemAlternative::where('treatment_plan_item_id', $item->id)
            ->pluck('option_code')
            ->all();

        foreach (range('A', 'Z') as $code) {
            if (! in_array($code, $used, true)) {
                return $code;
            }
        }

        throw ValidationException::withMessages([
            'option_code' => ['El ítem ya tiene 26 alternativas; no se pueden agregar más.'],
        ]);
    }
}
