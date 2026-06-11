<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge([
                'notes' => Str::limit(strip_tags($this->notes), 1000),
            ]);
        }
        
        if ($this->has('treatment_notes')) {
            $this->merge([
                'treatment_notes' => Str::limit(strip_tags($this->treatment_notes), 2000),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => 'sometimes|required|exists:patients,id',
            'user_id' => [
                'sometimes',
                'required',
                'exists:users,id',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
            'dental_chair_id' => 'sometimes|required|exists:dental_chairs,id',
            'appointment_type_id' => 'sometimes|required|exists:appointment_types,id',
            'scheduled_at' => 'sometimes|required|date',
            'duration_minutes' => 'sometimes|required|integer|min:15|max:480',
            'status' => 'sometimes|required|in:scheduled,confirmed,cancelled,completed,no_show,in_consultation',
            'notes' => 'sometimes|nullable|string|max:1000',
            'treatment_notes' => 'sometimes|nullable|string|max:2000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_id.exists' => 'El paciente seleccionado no existe.',
            'user_id.exists' => 'El profesional seleccionado no existe o está inactivo.',
            'dental_chair_id.exists' => 'La silla dental seleccionada no existe.',
            'appointment_type_id.exists' => 'El tipo de cita seleccionado no existe.',
            'scheduled_at.date' => 'La fecha y hora debe ser una fecha válida.',
            'duration_minutes.integer' => 'La duración debe ser un número entero.',
            'duration_minutes.min' => 'La duración mínima es de 15 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 480 minutos (8 horas).',
            'status.in' => 'El estado seleccionado no es válido.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
            'treatment_notes.max' => 'Las notas de tratamiento no pueden exceder 2000 caracteres.',
        ];
    }
}
