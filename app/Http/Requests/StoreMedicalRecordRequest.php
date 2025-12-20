<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StoreMedicalRecordRequest extends FormRequest
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
            'chief_complaint' => 1000,
            'medical_history' => 2000,
            'dental_history' => 2000,
            'allergies' => 1000,
            'medications' => 1000,
            'systemic_conditions' => 1000,
            'family_history' => 1000,
            'social_history' => 1000,
            'clinical_examination' => 2000,
            'diagnosis' => 1000,
            'treatment_plan' => 2000,
            'notes' => 1000,
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
            'patient_id' => 'required|exists:patients,id',
            'first_visit_date' => 'nullable|date|before_or_equal:today',
            'chief_complaint' => 'nullable|string|max:1000',
            'medical_history' => 'nullable|string|max:2000',
            'dental_history' => 'nullable|string|max:2000',
            'allergies' => 'nullable|string|max:1000',
            'medications' => 'nullable|string|max:1000',
            'systemic_conditions' => 'nullable|string|max:1000',
            'family_history' => 'nullable|string|max:1000',
            'social_history' => 'nullable|string|max:1000',
            'vital_signs' => 'nullable|array',
            'vital_signs.blood_pressure' => 'nullable|string|max:20',
            'vital_signs.heart_rate' => 'nullable|integer|min:30|max:200',
            'vital_signs.temperature' => 'nullable|numeric|min:30|max:45',
            'vital_signs.respiratory_rate' => 'nullable|integer|min:8|max:40',
            'clinical_examination' => 'nullable|string|max:2000',
            'diagnosis' => 'nullable|string|max:1000',
            'treatment_plan' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'El paciente es obligatorio',
            'patient_id.exists' => 'El paciente seleccionado no existe',
            'first_visit_date.before_or_equal' => 'La fecha de primera visita no puede ser futura',
            'vital_signs.heart_rate.min' => 'La frecuencia cardíaca mínima es 30 bpm',
            'vital_signs.heart_rate.max' => 'La frecuencia cardíaca máxima es 200 bpm',
            'vital_signs.temperature.min' => 'La temperatura mínima es 30°C',
            'vital_signs.temperature.max' => 'La temperatura máxima es 45°C',
            'vital_signs.respiratory_rate.min' => 'La frecuencia respiratoria mínima es 8 rpm',
            'vital_signs.respiratory_rate.max' => 'La frecuencia respiratoria máxima es 40 rpm'
        ];
    }
}
