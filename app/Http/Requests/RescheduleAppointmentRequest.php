<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RescheduleAppointmentRequest — PATCH /api/appointments/{appointment}/reschedule
 * (calendar-scheduling-redesign T1). Only scheduling fields are accepted: the new
 * start and an optional duration; professional, chair and patient never move here.
 */
class RescheduleAppointmentRequest extends FormRequest
{
    /**
     * Authorization is handled by the `role` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Same future rule as StoreAppointmentRequest: a reschedule places the
            // appointment in a new slot, so it follows the create-time policy.
            'scheduled_at' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    try {
                        $scheduledAt = \Carbon\Carbon::parse($value);
                        $minutesDifference = \Carbon\Carbon::now()->diffInMinutes($scheduledAt, false);

                        if ($minutesDifference < 1) {
                            $fail('La fecha y hora de la cita debe ser al menos 1 minuto en el futuro.');
                        }
                    } catch (\Exception $e) {
                        $fail('La fecha y hora proporcionada no es válida: ' . $e->getMessage());
                    }
                },
            ],
            'duration_minutes' => 'sometimes|required|integer|min:15|max:480',
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
            'scheduled_at.required' => 'La fecha y hora de la cita es requerida.',
            'scheduled_at.date' => 'La fecha y hora debe ser una fecha válida.',
            'duration_minutes.required' => 'La duración es requerida.',
            'duration_minutes.integer' => 'La duración debe ser un número entero.',
            'duration_minutes.min' => 'La duración mínima es de 15 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 480 minutos (8 horas).',
        ];
    }
}
