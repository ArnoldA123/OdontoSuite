<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * FormRequest for creating a SpecialtyRecord.
 *
 * Slice 02 / T-02.4, T-02.10 — BF-010 fix + new optional procedure_id:
 *  - authorize() is null-safe when `specialty` is absent (returns false)
 *  - procedure_id → integer, nullable, exists:procedure_catalog,id
 *
 * @see openspec/changes/bugfix-2026-08/specs/02-form-requests.md
 */
class StoreSpecialtyRecordRequest extends FormRequest
{
    use LocalizedErrors;

    /**
     * Determine if the user is authorized to make this request.
     *
     * BF-010: when `specialty` is null/absent, accessing $specialtyMap[null]
     * produced an undefined-key warning that Laravel surfaced as 500. The
     * null-coalesce ensures we always reach in_array() with an empty array.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $specialty = $this->input('specialty');
        if ($specialty === null || $specialty === '') {
            return false;
        }

        // Verificar que el usuario tenga la especialidad correspondiente
        $specialtyMap = [
            'implantologia' => ['implantologo', 'administrador'],
            'ortodoncia' => ['odontologo', 'administrador'],
            'endodoncia' => ['odontologo', 'administrador'],
            'rehabilitacion' => ['odontologo', 'administrador'],
            'cirugia_oral' => ['odontologo', 'administrador'],
        ];

        return in_array($user->role, $specialtyMap[$specialty] ?? []);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $textFields = [
            'complications' => 1000,
            'follow_up_notes' => 1000,
        ];

        foreach ($textFields as $field => $maxLength) {
            if ($this->has($field)) {
                $this->merge([
                    $field => Str::limit(strip_tags($this->$field), $maxLength),
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $specialty = $this->input('specialty');

        $baseRules = [
            'specialty' => 'required|in:implantologia,ortodoncia,endodoncia,rehabilitacion,cirugia_oral',
            'patient_id' => 'required|exists:patients,id',
            'appointment_id' => 'sometimes|nullable|exists:appointments,id',
            'dental_piece_id' => 'sometimes|nullable|exists:dental_pieces,id',
            'complications' => 'sometimes|nullable|string|max:1000',
            'follow_up_notes' => 'sometimes|nullable|string|max:1000',
            // Slice 02 / T-02.10 — BF-007 related: optional procedure.
            'procedure_id' => 'nullable|exists:procedure_catalog,id',
        ];

        // Reglas específicas por especialidad
        $specialtyRules = match ($specialty) {
            'implantologia' => [
                'dental_piece_id' => 'required|exists:dental_pieces,id',
                'implant_brand' => 'required|string|max:100',
                'implant_model' => 'required|string|max:100',
                'implant_diameter' => 'required|string|max:20',
                'implant_length' => 'required|string|max:20',
                'batch_number' => 'required|string|max:50',
                'serial_number' => 'sometimes|nullable|string|max:50',
                'placement_date' => 'required|date|before_or_equal:today',
                'healing_date' => 'sometimes|nullable|date|after:placement_date',
                'loading_date' => 'sometimes|nullable|date|after:healing_date',
                'status' => 'sometimes|nullable|in:placed,healing,loaded,failed,removed',
                'surgical_notes' => 'sometimes|nullable|string|max:2000',
                'post_surgical_notes' => 'sometimes|nullable|string|max:2000',
                'torque_value' => 'sometimes|nullable|numeric|min:0|max:999.99',
            ],
            'ortodoncia' => [
                'treatment_type' => 'required|string|max:50',
                'appliance_type' => 'sometimes|nullable|string|max:100',
                'treatment_start_date' => 'required|date|before_or_equal:today',
                'estimated_completion_date' => 'sometimes|nullable|date|after:treatment_start_date',
                'actual_completion_date' => 'sometimes|nullable|date|after:treatment_start_date',
                'treatment_phase' => 'sometimes|nullable|in:initial,active,retention,completed',
                'treatment_objectives' => 'sometimes|nullable|string|max:2000',
                'current_notes' => 'sometimes|nullable|string|max:2000',
                'activation_notes' => 'sometimes|nullable|string|max:2000',
                'progress_notes' => 'sometimes|nullable|string|max:2000',
                'retention_plan' => 'sometimes|nullable|string|max:1000',
            ],
            'endodoncia' => [
                'dental_piece_id' => 'required|exists:dental_pieces,id',
                'tooth_number' => 'required|string|max:10',
                'canal_count' => 'required|integer|min:1|max:4',
                'canal_lengths' => 'sometimes|nullable|array',
                'canal_diameters' => 'sometimes|nullable|array',
                'working_length_method' => 'sometimes|nullable|string|max:50',
                'pulp_diagnosis' => 'sometimes|nullable|string|max:500',
                'periapical_diagnosis' => 'sometimes|nullable|string|max:500',
                'treatment_plan' => 'sometimes|nullable|string|max:1000',
                'anesthesia_used' => 'sometimes|nullable|string|max:200',
                'access_cavity_notes' => 'sometimes|nullable|string|max:1000',
                'canal_preparation_notes' => 'sometimes|nullable|string|max:1000',
                'irrigation_protocol' => 'sometimes|nullable|string|max:1000',
                'medication_used' => 'sometimes|nullable|string|max:500',
                'obturation_technique' => 'sometimes|nullable|string|max:200',
                'obturation_materials' => 'sometimes|nullable|string|max:500',
                'treatment_status' => 'sometimes|nullable|in:in_progress,completed,failed,retreatment',
                'treatment_completion_date' => 'sometimes|nullable|date|before_or_equal:today',
            ],
            'rehabilitacion' => [
                'dental_piece_id' => 'required|exists:dental_pieces,id',
                'prosthesis_type' => 'required|string|max:50',
                'material_type' => 'required|string|max:50',
                'laboratory_name' => 'sometimes|nullable|string|max:100',
                'laboratory_contact' => 'sometimes|nullable|string|max:100',
                'impression_date' => 'sometimes|nullable|date',
                'delivery_date' => 'sometimes|nullable|date',
                'cementation_date' => 'sometimes|nullable|date',
                'shade_selection' => 'sometimes|nullable|string|max:200',
                'impression_notes' => 'sometimes|nullable|string|max:1000',
                'try_in_notes' => 'sometimes|nullable|string|max:1000',
                'adjustment_notes' => 'sometimes|nullable|string|max:1000',
                'cementation_notes' => 'sometimes|nullable|string|max:1000',
                'patient_satisfaction' => 'sometimes|nullable|string|max:1000',
                'status' => 'sometimes|nullable|in:impression,laboratory,try_in,delivered,cemented,failed',
                'follow_up_notes' => 'sometimes|nullable|string|max:1000',
            ],
            'cirugia_oral' => [
                'procedure_type' => 'required|string|max:50',
                'surgery_site' => 'required|string|max:100',
                'surgery_date' => 'sometimes|nullable|date|before_or_equal:today',
                'surgery_duration_minutes' => 'sometimes|nullable|integer|min:1|max:480',
                'anesthesia_type' => 'sometimes|nullable|string|max:200',
                'surgical_technique' => 'sometimes|nullable|string|max:2000',
                'sutures_used' => 'sometimes|nullable|string|max:100',
                'suture_count' => 'sometimes|nullable|integer|min:0|max:50',
                'hemostasis_method' => 'sometimes|nullable|string|max:200',
                'surgical_notes' => 'sometimes|nullable|string|max:2000',
                'post_surgical_instructions' => 'sometimes|nullable|string|max:2000',
                'medications_prescribed' => 'sometimes|nullable|string|max:1000',
                'recovery_notes' => 'sometimes|nullable|string|max:2000',
                'follow_up_date' => 'sometimes|nullable|date',
                'follow_up_notes' => 'sometimes|nullable|string|max:1000',
                'status' => 'sometimes|nullable|in:scheduled,in_progress,completed,cancelled,complications',
            ],
            default => []
        };

        return array_merge($baseRules, $specialtyRules);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'specialty.required' => 'La especialidad es obligatoria',
            'specialty.in' => 'La especialidad seleccionada no es válida',
            'patient_id.required' => 'El paciente es obligatorio',
            'patient_id.exists' => 'El paciente seleccionado no existe',
            'appointment_id.exists' => 'La cita seleccionada no existe',
            'dental_piece_id.exists' => 'La pieza dental seleccionada no existe',
            // Slice 02 / T-02.10 — es message for the new optional field.
            'procedure_id.exists' => 'El procedimiento seleccionado no existe',
            'dental_piece_id.required' => 'La pieza dental es obligatoria',
            'implant_brand.required' => 'La marca del implante es obligatoria',
            'implant_model.required' => 'El modelo del implante es obligatorio',
            'placement_date.required' => 'La fecha de colocación es obligatoria',
            'placement_date.before_or_equal' => 'La fecha de colocación no puede ser futura',
            'treatment_type.required' => 'El tipo de tratamiento es obligatorio',
            'treatment_start_date.required' => 'La fecha de inicio del tratamiento es obligatoria',
            'tooth_number.required' => 'El número de diente es obligatorio',
            'canal_count.required' => 'El número de conductos es obligatorio',
            'prosthesis_type.required' => 'El tipo de prótesis es obligatorio',
            'material_type.required' => 'El tipo de material es obligatorio',
            'procedure_type.required' => 'El tipo de procedimiento es obligatorio',
            'surgery_site.required' => 'El sitio quirúrgico es obligatorio',
            'surgery_date.date' => 'La fecha de la cirugía no es una fecha válida',
            'surgery_date.before_or_equal' => 'La fecha de la cirugía no puede ser futura',
        ];
    }
}
