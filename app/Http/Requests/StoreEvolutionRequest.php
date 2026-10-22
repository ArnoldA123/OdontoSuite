<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreEvolutionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()->role, ['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente']);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $textFields = [
            'subjective' => 2000,
            'objective' => 2000,
            'assessment' => 2000,
            'plan' => 2000,
            'procedures_performed' => 2000,
            'materials_used' => 1000,
            'prescriptions' => 1000,
            'recommendations' => 1000,
            'next_appointment_notes' => 1000,
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
        return [
            'appointment_id' => 'sometimes|nullable|exists:appointments,id',
            'evolution_date' => 'sometimes|nullable|date|before_or_equal:today',
            'specialty' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::exists('specialties', 'code'),
            ],
            'subjective' => 'sometimes|nullable|string|max:2000',
            'objective' => 'sometimes|nullable|string|max:2000',
            'assessment' => 'sometimes|nullable|string|max:2000',
            'plan' => 'sometimes|nullable|string|max:2000',
            'procedures_performed' => 'sometimes|nullable|string|max:2000',
            'materials_used' => 'sometimes|nullable|string|max:1000',
            'prescriptions' => 'sometimes|nullable|string|max:1000',
            'recommendations' => 'sometimes|nullable|string|max:1000',
            'next_appointment_notes' => 'sometimes|nullable|string|max:1000',
            'vital_signs' => 'sometimes|nullable|array',
            'vital_signs.blood_pressure' => 'sometimes|nullable|string|max:20',
            'vital_signs.heart_rate' => 'sometimes|nullable|integer|min:30|max:200',
            'vital_signs.temperature' => 'sometimes|nullable|numeric|min:30|max:45',
            'vital_signs.respiratory_rate' => 'sometimes|nullable|integer|min:8|max:40',
            // Contract (issue #222): flat, unit-bearing periodontal measures.
            // The `array:` rule rejects undeclared keys so the shape cannot
            // drift key by key the way it did before the interface exposed it.
            'clinical_measurements' => [
                'sometimes',
                'nullable',
                'array:bleeding_on_probing,gingival_recession_mm,plaque_index_percent,pocket_depth_mm,tooth_mobility',
            ],
            'clinical_measurements.pocket_depth_mm' => 'sometimes|nullable|numeric|min:0|max:20',
            'clinical_measurements.gingival_recession_mm' => 'sometimes|nullable|numeric|min:0|max:20',
            'clinical_measurements.plaque_index_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'clinical_measurements.bleeding_on_probing' => 'sometimes|nullable|boolean',
            'clinical_measurements.tooth_mobility' => 'sometimes|nullable|integer|min:0|max:3',
            'requires_follow_up' => 'boolean',
            'follow_up_date' => 'sometimes|nullable|date|after:evolution_date',
            // #296: only a reviewed analysis can be cited, and the service
            // checks patient, status and annulment before anything is written.
            'cited_analysis_ids' => 'sometimes|array|max:20',
            'cited_analysis_ids.*' => 'integer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'appointment_id.exists' => 'La cita seleccionada no existe',
            'specialty.exists' => 'La especialidad seleccionada no existe en el catálogo',
            'evolution_date.before_or_equal' => 'La fecha de evolución no puede ser futura',
            'follow_up_date.after' => 'La fecha de seguimiento debe ser posterior a la fecha de evolución',
            'vital_signs.heart_rate.min' => 'La frecuencia cardíaca mínima es 30 bpm',
            'vital_signs.heart_rate.max' => 'La frecuencia cardíaca máxima es 200 bpm',
            'vital_signs.temperature.min' => 'La temperatura mínima es 30°C',
            'vital_signs.temperature.max' => 'La temperatura máxima es 45°C',
            'vital_signs.respiratory_rate.min' => 'La frecuencia respiratoria mínima es 8 rpm',
            'vital_signs.respiratory_rate.max' => 'La frecuencia respiratoria máxima es 40 rpm',
            'clinical_measurements.array' => 'Las mediciones clínicas contienen una clave que no pertenece al contrato',
            'clinical_measurements.pocket_depth_mm.min' => 'La profundidad de sondaje mínima es 0 mm',
            'clinical_measurements.pocket_depth_mm.max' => 'La profundidad de sondaje máxima es 20 mm',
            'clinical_measurements.gingival_recession_mm.min' => 'La recesión gingival mínima es 0 mm',
            'clinical_measurements.gingival_recession_mm.max' => 'La recesión gingival máxima es 20 mm',
            'clinical_measurements.plaque_index_percent.min' => 'El índice de placa mínimo es 0%',
            'clinical_measurements.plaque_index_percent.max' => 'El índice de placa máximo es 100%',
            'clinical_measurements.bleeding_on_probing.boolean' => 'El sangrado al sondaje debe ser verdadero o falso',
            'clinical_measurements.tooth_mobility.min' => 'La movilidad dental mínima es el grado 0',
            'clinical_measurements.tooth_mobility.max' => 'La movilidad dental máxima es el grado 3',
        ];
    }
}
