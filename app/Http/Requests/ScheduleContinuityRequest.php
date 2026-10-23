<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleContinuityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => 'required|date|after:now',
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
            'dental_chair_id' => 'required|exists:dental_chairs,id',
            'appointment_type_id' => 'nullable|exists:appointment_types,id',
            'duration_minutes' => 'sometimes|integer|min:15|max:480',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_at.required' => 'La fecha y hora de la cita es obligatoria.',
            'scheduled_at.after' => 'La fecha y hora de la cita debe ser futura.',
            'user_id.required' => 'El profesional es obligatorio.',
            'user_id.exists' => 'El profesional seleccionado no existe o está inactivo.',
            'dental_chair_id.required' => 'El sillón es obligatorio.',
            'dental_chair_id.exists' => 'El sillón seleccionado no existe.',
            'appointment_type_id.exists' => 'El tipo de cita seleccionado no existe.',
            'duration_minutes.min' => 'La duración mínima es de 15 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 480 minutos.',
        ];
    }
}
