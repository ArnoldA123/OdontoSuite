<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
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
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
            'dental_chair_id' => 'required|exists:dental_chairs,id',
            'appointment_type_id' => 'required|exists:appointment_types,id',
            'scheduled_at' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    try {
                        $scheduledAt = \Carbon\Carbon::parse($value);
                        $now = \Carbon\Carbon::now();
                        
                        // Log para depuración
                        \Log::info('Validating scheduled_at', [
                            'received_value' => $value,
                            'parsed_scheduled_at' => $scheduledAt->toDateTimeString(),
                            'now' => $now->toDateTimeString(),
                            'timezone' => $scheduledAt->timezone->getName(),
                            'diff_in_minutes' => $now->diffInMinutes($scheduledAt, false),
                            'is_future' => $scheduledAt->isFuture(),
                        ]);
                        
                        // Permitir citas que sean al menos 1 minuto en el futuro
                        // Usar diffInMinutes para evitar problemas de zona horaria
                        $minutesDifference = $now->diffInMinutes($scheduledAt, false);
                        
                        if ($minutesDifference < 1) {
                            $fail('La fecha y hora de la cita debe ser al menos 1 minuto en el futuro. Fecha seleccionada: ' . $scheduledAt->format('Y-m-d H:i:s') . ' (' . $scheduledAt->timezone->getName() . '), Hora actual: ' . $now->format('Y-m-d H:i:s') . ' (' . $now->timezone->getName() . '), Diferencia: ' . $minutesDifference . ' minutos');
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error validating scheduled_at', [
                            'value' => $value,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $fail('La fecha y hora proporcionada no es válida: ' . $e->getMessage());
                    }
                },
            ],
            'duration_minutes' => 'required|integer|min:15|max:480',
            'status' => 'sometimes|nullable|in:scheduled,confirmed,in_consultation,completed,cancelled,no_show,rescheduled',
            'notes' => 'sometimes|nullable|string|max:1000',
            'idempotency_key' => 'sometimes|nullable|string|max:255',
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
            'patient_id.required' => 'El paciente es requerido.',
            'patient_id.exists' => 'El paciente seleccionado no existe.',
            'user_id.required' => 'El profesional es requerido.',
            'user_id.exists' => 'El profesional seleccionado no existe o está inactivo.',
            'dental_chair_id.required' => 'La silla dental es requerida.',
            'dental_chair_id.exists' => 'La silla dental seleccionada no existe.',
            'appointment_type_id.required' => 'El tipo de cita es requerido.',
            'appointment_type_id.exists' => 'El tipo de cita seleccionado no existe.',
            'scheduled_at.required' => 'La fecha y hora de la cita es requerida.',
            'scheduled_at.date' => 'La fecha y hora debe ser una fecha válida.',
            'scheduled_at.after' => 'La fecha y hora de la cita debe ser en el futuro.',
            'duration_minutes.required' => 'La duración es requerida.',
            'duration_minutes.integer' => 'La duración debe ser un número entero.',
            'duration_minutes.min' => 'La duración mínima es de 15 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 480 minutos (8 horas).',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
        ];
    }
}
