<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * FormRequest for creating an Appointment.
 *
 * Optional/nullable fields added in bugfix-2026-08/slice-02 (BF-007):
 *  - procedure_id      → integer, nullable, exists:procedure_catalog,id
 *  - treatment_plan_id → integer, nullable, exists:treatment_plans,id
 *  - branch_id         → integer, nullable, exists:branches,id
 *  - ends_at           → date, nullable (computed from scheduled_at + duration)
 *
 * Canonical status enum post-fix-migration: only 'in_consultation' is valid
 * (DB migrated AWAY from 'in_progress' in 2025_10_14_fix_appointments_status_enum).
 *
 * @see docs/decisions/0008-procedure-catalog-legacy-specialty.md (context)
 */
class StoreAppointmentRequest extends FormRequest
{
    use LocalizedErrors;

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
            // Slice 02 / T-02.1 — BF-007: optional related IDs (additive only).
            'procedure_id' => 'nullable|exists:procedure_catalog,id',
            'treatment_plan_id' => 'nullable|exists:treatment_plans,id',
            'branch_id' => 'nullable|exists:branches,id',
            'ends_at' => 'nullable|date',
            'scheduled_at' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    try {
                        $scheduledAt = \Carbon\Carbon::parse($value);
                        $now = \Carbon\Carbon::now();

                        // Permitir citas que sean al menos 1 minuto en el futuro
                        // Usar diffInMinutes para evitar problemas de zona horaria
                        $minutesDifference = $now->diffInMinutes($scheduledAt, false);

                        if ($minutesDifference < 1) {
                            $fail('La fecha y hora de la cita debe ser al menos 1 minuto en el futuro. Fecha seleccionada: ' . $scheduledAt->format('Y-m-d H:i:s') . ' (' . $scheduledAt->timezone->getName() . '), Hora actual: ' . $now->format('Y-m-d H:i:s') . ' (' . $now->timezone->getName() . '), Diferencia: ' . $minutesDifference . ' minutos');
                        }
                    } catch (\Exception $e) {
                        $fail('La fecha y hora proporcionada no es válida: ' . $e->getMessage());
                    }
                },
            ],
            'duration_minutes' => 'required|integer|min:15|max:480',
            // BF-008: canonical enum (DB migrated from 'in_progress' to 'in_consultation').
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
            // Slice 02 — new optional related IDs (es messages).
            'procedure_id.exists' => 'El procedimiento seleccionado no existe.',
            'treatment_plan_id.exists' => 'El plan de tratamiento seleccionado no existe.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'ends_at.date' => 'La fecha de fin debe ser una fecha válida.',
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
