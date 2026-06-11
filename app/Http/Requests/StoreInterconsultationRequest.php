<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StoreInterconsultationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()->role, ['administrador', 'odontologo', 'implantologo', 'tecnico_dental']);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $textFields = [
            'specialty_from' => 50,
            'specialty_to' => 50,
            'reason' => 1000,
            'clinical_question' => 1000,
            'clinical_data' => 2000,
            'requested_studies' => 1000,
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
            'to_specialist_id' => 'required|exists:users,id|different:' . Auth::id(),
            'appointment_id' => 'sometimes|nullable|exists:appointments,id',
            'specialty_from' => 'required|string|max:50',
            'specialty_to' => 'required|string|max:50',
            'reason' => 'sometimes|nullable|string|max:1000',
            'clinical_question' => 'sometimes|nullable|string|max:1000',
            'clinical_data' => 'sometimes|nullable|string|max:2000',
            'requested_studies' => 'sometimes|nullable|string|max:1000',
            'priority' => 'required|in:low,medium,high,urgent',
            'requested_date' => 'sometimes|nullable|date|before_or_equal:today'
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
            'to_specialist_id.required' => 'El especialista destinatario es obligatorio',
            'to_specialist_id.exists' => 'El especialista seleccionado no existe',
            'to_specialist_id.different' => 'No puedes enviar una interconsulta a ti mismo',
            'appointment_id.exists' => 'La cita seleccionada no existe',
            'specialty_from.required' => 'La especialidad de origen es obligatoria',
            'specialty_to.required' => 'La especialidad de destino es obligatoria',
            'priority.required' => 'La prioridad es obligatoria',
            'priority.in' => 'La prioridad seleccionada no es válida',
            'requested_date.before_or_equal' => 'La fecha de solicitud no puede ser futura'
        ];
    }
}
