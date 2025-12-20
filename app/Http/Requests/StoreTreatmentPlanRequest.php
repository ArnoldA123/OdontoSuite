<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StoreTreatmentPlanRequest extends FormRequest
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
        if ($this->has('title')) {
            $this->merge([
                'title' => Str::limit(strip_tags($this->title), 200),
            ]);
        }
        
        if ($this->has('description')) {
            $this->merge([
                'description' => strip_tags($this->description),
            ]);
        }
        
        if ($this->has('notes')) {
            $this->merge([
                'notes' => Str::limit(strip_tags($this->notes), 1000),
            ]);
        }
        
        if ($this->has('patient_notes')) {
            $this->merge([
                'patient_notes' => Str::limit(strip_tags($this->patient_notes), 1000),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'estimated_duration_weeks' => 'nullable|integer|min:1|max:104',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string|max:1000',
            'patient_notes' => 'nullable|string|max:1000',
            'phases' => 'nullable|array',
            'phases.*' => 'string|max:100',
            'requires_anesthesia' => 'boolean',
            'is_urgent' => 'boolean',
            'items' => 'nullable|array|max:50',
            'items.*.description' => 'required_with:items|string|max:200',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01|max:999.99',
            'items.*.unit_price' => 'required_with:items|numeric|min:0|max:99999.99',
            'items.*.category' => 'nullable|string|max:50'
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
            'title.required' => 'El título del plan es obligatorio',
            'title.max' => 'El título no puede exceder 200 caracteres',
            'estimated_duration_weeks.integer' => 'La duración debe ser un número entero',
            'estimated_duration_weeks.min' => 'La duración mínima es 1 semana',
            'estimated_duration_weeks.max' => 'La duración máxima es 104 semanas (2 años)',
            'start_date.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'items.max' => 'No se pueden agregar más de 50 procedimientos',
            'items.*.description.required_with' => 'La descripción del procedimiento es obligatoria',
            'items.*.quantity.required_with' => 'La cantidad es obligatoria',
            'items.*.quantity.min' => 'La cantidad mínima es 0.01',
            'items.*.unit_price.required_with' => 'El precio unitario es obligatorio',
            'items.*.unit_price.min' => 'El precio no puede ser negativo'
        ];
    }
}
