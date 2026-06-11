<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
            'specialty' => 'sometimes|nullable|string|max:50',
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
            'clinical_measurements' => 'sometimes|nullable|array',
            'requires_follow_up' => 'boolean',
            'follow_up_date' => 'sometimes|nullable|date|after:evolution_date'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'appointment_id.exists' => 'La cita seleccionada no existe',
            'evolution_date.before_or_equal' => 'La fecha de evolución no puede ser futura',
            'follow_up_date.after' => 'La fecha de seguimiento debe ser posterior a la fecha de evolución',
            'vital_signs.heart_rate.min' => 'La frecuencia cardíaca mínima es 30 bpm',
            'vital_signs.heart_rate.max' => 'La frecuencia cardíaca máxima es 200 bpm',
            'vital_signs.temperature.min' => 'La temperatura mínima es 30°C',
            'vital_signs.temperature.max' => 'La temperatura máxima es 45°C',
            'vital_signs.respiratory_rate.min' => 'La frecuencia respiratoria mínima es 8 rpm',
            'vital_signs.respiratory_rate.max' => 'La frecuencia respiratoria máxima es 40 rpm'
        ];
    }
}
