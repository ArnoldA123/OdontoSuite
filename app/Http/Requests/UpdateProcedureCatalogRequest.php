<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedureCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'administrador';
    }

    public function rules(): array
    {
        $procedureId = $this->route('id');

        return [
            'code' => "sometimes|string|max:50|unique:procedure_catalog,code,{$procedureId}",
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'specialty_id' => 'sometimes|nullable|integer|exists:specialties,id',
            'legacy_specialty' => 'sometimes|nullable|string|max:50',
            'default_cost' => 'sometimes|required|numeric|min:0|max:999999.99',
            'default_duration_minutes' => 'sometimes|nullable|integer|min:5|max:480',
            'is_active' => 'sometimes|boolean',
            'requires_anesthesia' => 'sometimes|boolean',
            'materials' => 'sometimes|nullable|array',
            'materials.*' => 'string|max:200',
            'contraindications' => 'sometimes|nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del procedimiento es obligatorio.',
            'code.unique' => 'Ya existe un procedimiento con este codigo.',
            'specialty_id.exists' => 'La especialidad seleccionada no existe.',
            'default_cost.numeric' => 'El costo debe ser un numero valido.',
            'default_cost.min' => 'El costo no puede ser negativo.',
            'default_duration_minutes.min' => 'La duracion minima es 5 minutos.',
            'default_duration_minutes.max' => 'La duracion maxima es 480 minutos (8 horas).',
        ];
    }
}
