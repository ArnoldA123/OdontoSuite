<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F2 (#282) — the closure payload.
 *
 * One closure serves both doors: the appointment's act
 * (`POST /api/appointments/{id}/complete`) and the urgency act
 * (`POST /api/encounters/{id}/complete`). Keeping the rules in one place is what
 * makes "cierre único" true at the contract level and not only in the service.
 */
class CloseClinicalEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is the route's `permission:` middleware; this request
        // only owns the shape of the payload.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            // #295 S7: el item declara de que procedimiento del catalogo sale.
            // Sin esta regla `validated()` lo descartaba y el acto no podia
            // derivar la exigencia de materiales desde sus propios items.
            'treatment_plan.items.*.procedure_catalog_id' => 'sometimes|nullable|integer|exists:procedure_catalog,id',
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
            'materials.*.product_id' => 'required_with:materials|integer|exists:products,id',
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
            'generate_quotation' => 'sometimes|boolean',
        ];
    }
}
