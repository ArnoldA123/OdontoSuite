<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'administrador';
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:procedure_catalog,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'specialty_id' => 'nullable|integer|exists:specialties,id',
            'legacy_specialty' => 'nullable|string|max:50',
            'default_cost' => 'required|numeric|min:0|max:999999.99',
            'default_duration_minutes' => 'nullable|integer|min:5|max:480',
            'is_active' => 'boolean',
            'requires_anesthesia' => 'boolean',
            'materials' => 'nullable|array',
            'materials.*' => 'string|max:200',
            'contraindications' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El codigo del procedimiento es obligatorio.',
            'code.unique' => 'Ya existe un procedimiento con este codigo.',
            'code.max' => 'El codigo no puede exceder 50 caracteres.',
            'name.required' => 'El nombre del procedimiento es obligatorio.',
            'name.max' => 'El nombre no puede exceder 200 caracteres.',
            'description.max' => 'La descripcion no puede exceder 1000 caracteres.',
            'specialty_id.exists' => 'La especialidad seleccionada no existe.',
            'default_cost.required' => 'El costo por defecto es obligatorio.',
            'default_cost.numeric' => 'El costo debe ser un numero valido.',
            'default_cost.min' => 'El costo no puede ser negativo.',
            'default_duration_minutes.min' => 'La duracion minima es 5 minutos.',
            'default_duration_minutes.max' => 'La duracion maxima es 480 minutos (8 horas).',
        ];
    }
}
