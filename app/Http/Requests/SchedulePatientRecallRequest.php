<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchedulePatientRecallRequest extends FormRequest
{
    use LocalizedErrors;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'user_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'dental_chair_id' => ['required', 'exists:dental_chairs,id'],
            'appointment_type_id' => ['required', 'exists:appointment_types,id'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_at.required' => 'La fecha y hora de la cita es requerida.',
            'scheduled_at.date' => 'La fecha y hora debe ser una fecha válida.',
            'user_id.required' => 'El profesional es requerido.',
            'user_id.exists' => 'El profesional seleccionado no existe o está inactivo.',
            'dental_chair_id.required' => 'La silla dental es requerida.',
            'dental_chair_id.exists' => 'La silla dental seleccionada no existe.',
            'appointment_type_id.required' => 'El tipo de cita es requerido.',
            'appointment_type_id.exists' => 'El tipo de cita seleccionado no existe.',
            'duration_minutes.required' => 'La duración es requerida.',
            'duration_minutes.integer' => 'La duración debe ser un número entero.',
            'duration_minutes.min' => 'La duración mínima es de 15 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 480 minutos.',
            'notes.string' => 'Las notas deben ser texto.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
        ];
    }
}
