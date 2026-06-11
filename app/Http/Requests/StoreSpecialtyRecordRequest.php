<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StoreSpecialtyRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        $specialty = $this->input('specialty');

        // Verificar que el usuario tenga la especialidad correspondiente
        $specialtyMap = [
            'implantologia' => ['implantologo', 'administrador'],
            'ortodoncia' => ['odontologo', 'administrador'],
            'endodoncia' => ['odontologo', 'administrador'],
            'rehabilitacion' => ['odontologo', 'administrador'],
            'cirugia_oral' => ['odontologo', 'administrador']
        ];

        return in_array($user->role, $specialtyMap[$specialty] ?? []);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $textFields = [
            'notes' => 1000,
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
            'notes' => 'sometimes|nullable|string|max:1000',
            'complications' => 'sometimes|nullable|string|max:1000',
            'follow_up_notes' => 'sometimes|nullable|string|max:1000'
        ];

        // Reglas específicas por especialidad
        $specialtyRules = match ($specialty) {
            'implantologia' => [
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
                'torque_value' => 'sometimes|nullable|numeric|min:0|max:999.99'
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
                'retention_plan' => 'sometimes|nullable|string|max:1000'
            ],
            'endodoncia' => [
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
                'treatment_completion_date' => 'sometimes|nullable|date|before_or_equal:today'
            ],
            'rehabilitacion' => [
                'restoration_type' => 'required|string|max:50',
                'material_type' => 'required|string|max:50',
                'color_shade' => 'sometimes|nullable|string|max:20',
                'laboratory_name' => 'sometimes|nullable|string|max:100',
                'laboratory_contact' => 'sometimes|nullable|string|max:100',
                'impression_date' => 'sometimes|nullable|date|before_or_equal:today',
                'try_in_date' => 'sometimes|nullable|date|after:impression_date',
                'cementation_date' => 'sometimes|nullable|date|after:try_in_date',
                'occlusion_type' => 'sometimes|nullable|string|max:50',
                'bite_registration' => 'sometimes|nullable|string|max:200',
                'shade_selection' => 'sometimes|nullable|string|max:200',
                'laboratory_notes' => 'sometimes|nullable|string|max:1000',
                'try_in_notes' => 'sometimes|nullable|string|max:1000',
                'cementation_notes' => 'sometimes|nullable|string|max:1000',
                'warranty_period' => 'sometimes|nullable|integer|min:0|max:120',
                'status' => 'sometimes|nullable|in:in_progress,in_laboratory,ready_for_try_in,try_in_scheduled,completed,failed'
            ],
            'cirugia_oral' => [
                'procedure_type' => 'required|string|max:50',
                'surgery_date' => 'required|date|before_or_equal:today',
                'surgery_duration_minutes' => 'sometimes|nullable|integer|min:1|max:480',
                'anesthesia_type' => 'sometimes|nullable|string|max:50',
                'anesthesia_duration_minutes' => 'sometimes|nullable|integer|min:1|max:480',
                'surgical_technique' => 'sometimes|nullable|string|max:200',
                'incision_type' => 'sometimes|nullable|string|max:100',
                'suture_material' => 'sometimes|nullable|string|max:100',
                'suture_technique' => 'sometimes|nullable|string|max:100',
                'suture_count' => 'sometimes|nullable|integer|min:0|max:50',
                'bleeding_control' => 'sometimes|nullable|string|max:200',
                'post_surgical_instructions' => 'sometimes|nullable|string|max:2000',
                'medications_prescribed' => 'sometimes|nullable|string|max:1000',
                'follow_up_schedule' => 'sometimes|nullable|string|max:500',
                'healing_assessment' => 'sometimes|nullable|string|max:1000',
                'suture_removal_date' => 'sometimes|nullable|date|after:surgery_date',
                'final_assessment' => 'sometimes|nullable|string|max:1000',
                'status' => 'sometimes|nullable|in:scheduled,in_progress,completed,in_recovery,follow_up,healed'
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
            'implant_brand.required' => 'La marca del implante es obligatoria',
            'implant_model.required' => 'El modelo del implante es obligatorio',
            'placement_date.required' => 'La fecha de colocación es obligatoria',
            'placement_date.before_or_equal' => 'La fecha de colocación no puede ser futura',
            'treatment_type.required' => 'El tipo de tratamiento es obligatorio',
            'treatment_start_date.required' => 'La fecha de inicio del tratamiento es obligatoria',
            'tooth_number.required' => 'El número de diente es obligatorio',
            'canal_count.required' => 'El número de conductos es obligatorio',
            'restoration_type.required' => 'El tipo de restauración es obligatorio',
            'material_type.required' => 'El tipo de material es obligatorio',
            'procedure_type.required' => 'El tipo de procedimiento es obligatorio',
            'surgery_date.required' => 'La fecha de cirugía es obligatoria',
            'surgery_date.before_or_equal' => 'La fecha de cirugía no puede ser futura'
        ];
    }
}
