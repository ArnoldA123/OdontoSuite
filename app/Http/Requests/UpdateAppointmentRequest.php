<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use App\Models\Appointment;
use App\Models\DentalChair;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * FormRequest for updating an Appointment.
 *
 * Slice 02 / T-02.12: ends_at must be after starts_at (server-side enforcement).
 *
 * citas-privacidad (T4): una edición parcial no reenvía a los participantes, así
 * que la comparación de sedes completa los valores que faltan con los de la
 * cita guardada. Un actor restringido solo asigna a su sede —nombrar otra es 403
 * (ADR-0010)— y una pareja con sedes distintas responde 422 en alcance de grupo.
 *
 * @see openspec/changes/bugfix-2026-08/specs/02-form-requests.md
 */
class UpdateAppointmentRequest extends FormRequest
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

        if ($this->has('treatment_notes')) {
            $this->merge([
                'treatment_notes' => Str::limit(strip_tags($this->treatment_notes), 2000),
            ]);
        }
    }

    /**
     * citas-privacidad (T4) — la sede de los participantes, resuelta contra la
     * cita guardada cuando el payload solo cambia uno de los dos lados.
     *
     * El rechazo por sede ajena de un actor restringido es de autorización
     * (403), la misma respuesta que el guard de escritura del modelo.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $appointment = $this->route('appointment');
            $existing = $appointment instanceof Appointment ? $appointment : null;

            $professionalId = $this->input('user_id', $existing?->user_id);
            $chairId = $this->input('dental_chair_id', $existing?->dental_chair_id);

            if (! is_scalar($professionalId) || ! is_scalar($chairId)) {
                return;
            }

            $professionalBranch = User::whereKey($professionalId)->value('branch_id');
            $chairBranch = DentalChair::whereKey($chairId)->value('branch_id');
            $actorBranch = $this->user()?->branch_id;

            if ($actorBranch !== null) {
                if ($professionalBranch !== null && (int) $professionalBranch !== (int) $actorBranch) {
                    throw new AuthorizationException('El profesional seleccionado no pertenece a su sede.');
                }

                if ($chairBranch !== null && (int) $chairBranch !== (int) $actorBranch) {
                    throw new AuthorizationException('El ambiente seleccionado no pertenece a su sede.');
                }

                return;
            }

            if ($professionalBranch !== null && $chairBranch !== null
                && (int) $professionalBranch !== (int) $chairBranch) {
                $validator->errors()->add(
                    'dental_chair_id',
                    'El ambiente y el profesional deben pertenecer a la misma sede.'
                );
            }
        });
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
            // BF-008: canonical enum matches DB (in_consultation is canonical).
            'status' => 'sometimes|required|in:scheduled,confirmed,cancelled,no_show,rescheduled',
            // #394: el mismo contrato que la puerta de estado. Cancelar por la
            // edición de la cita tampoco puede quedar sin motivo.
            'cancellation_reason' => 'required_if:status,cancelled|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string|max:1000',
            'treatment_notes' => 'sometimes|nullable|string|max:2000',
            // Slice 02 / T-02.12: ends_at must be after starts_at when both present.
            'ends_at' => 'nullable|date|after:scheduled_at',
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
            'cancellation_reason.required_if' => 'Indica el motivo de la cancelación.',
            'cancellation_reason.string' => 'El motivo de la cancelación debe ser un texto.',
            'cancellation_reason.max' => 'El motivo de la cancelación no puede exceder 255 caracteres.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
            'treatment_notes.max' => 'Las notas de tratamiento no pueden exceder 2000 caracteres.',
            'ends_at.date' => 'La fecha de fin debe ser una fecha válida.',
            'ends_at.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ];
    }
}
