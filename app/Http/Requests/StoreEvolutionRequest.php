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
            'appointment_id' => 'nullable|exists:appointments,id',
            'evolution_date' => 'nullable|date|before_or_equal:today',
            'specialty' => 'nullable|string|max:50',
            'subjective' => 'nullable|string|max:2000',
            'objective' => 'nullable|string|max:2000',
            'assessment' => 'nullable|string|max:2000',
            'plan' => 'nullable|string|max:2000',
            'procedures_performed' => 'nullable|string|max:2000',
            'materials_used' => 'nullable|string|max:1000',
            'prescriptions' => 'nullable|string|max:1000',
            'recommendations' => 'nullable|string|max:1000',
            'next_appointment_notes' => 'nullable|string|max:1000',
            'vital_signs' => 'nullable|array',
            'vital_signs.blood_pressure' => 'nullable|string|max:20',
            'vital_signs.heart_rate' => 'nullable|integer|min:30|max:200',
            'vital_signs.temperature' => 'nullable|numeric|min:30|max:45',
            'vital_signs.respiratory_rate' => 'nullable|integer|min:8|max:40',
            'clinical_measurements' => 'nullable|array',
            'requires_follow_up' => 'boolean',
            'follow_up_date' => 'nullable|date|after:evolution_date'
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
