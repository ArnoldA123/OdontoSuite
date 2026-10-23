<?php

namespace App\Http\Requests;

use App\Models\ProcedureCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // #293 S1: aplicabilidad del procedimiento (none = paciente completo).
            'site_type' => ['sometimes', 'required', Rule::in(ProcedureCatalog::SITE_TYPES)],
            'default_cost' => 'sometimes|required|numeric|min:0|max:999999.99',
            'default_duration_minutes' => 'sometimes|nullable|integer|min:5|max:480',
            'is_active' => 'sometimes|boolean',
            'requires_anesthesia' => 'sometimes|boolean',
            'requires_radiographs' => 'sometimes|boolean',
            'materials' => 'sometimes|nullable|array',
            'materials.*' => 'string|max:200',
            'requirements' => 'sometimes|nullable|string|max:2000',
            'materials_needed' => 'sometimes|nullable|string|max:2000',
            'steps' => 'sometimes|nullable|array',
            'steps.*' => 'string|max:500',
            'contraindications' => 'nullable|string|max:2000',
            'post_procedure_care' => 'sometimes|nullable|string|max:2000',
            // #293 S7: la edicion reemplaza el conjunto completo de equivalencias.
            'external_codes' => 'sometimes|nullable|array|max:20',
            'external_codes.*.system' => ['required_with:external_codes', 'string', 'max:40', 'regex:/^[a-z0-9_.-]+$/'],
            'external_codes.*.code' => 'required_with:external_codes|string|max:40',
            'external_codes.*.description' => 'sometimes|nullable|string|max:200',
            // #295 S4: enviar la receta la reemplaza completa, igual que las equivalencias.
            'material_recipe' => 'sometimes|nullable|array|max:50',
            'material_recipe.*.product_id' => 'required_with:material_recipe|integer|distinct|exists:products,id',
            'material_recipe.*.quantity' => 'required_with:material_recipe|numeric|gt:0|max:999999999.999',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del procedimiento es obligatorio.',
            'code.unique' => 'Ya existe un procedimiento con este codigo.',
            'specialty_id.exists' => 'La especialidad seleccionada no existe.',
            'site_type.in' => 'La aplicabilidad debe ser: sitio independiente, diente, superficie, cuadrante o arcada.',
            'default_cost.numeric' => 'El costo debe ser un numero valido.',
            'default_cost.min' => 'El costo no puede ser negativo.',
            'default_duration_minutes.min' => 'La duracion minima es 5 minutos.',
            'default_duration_minutes.max' => 'La duracion maxima es 480 minutos (8 horas).',
            'external_codes.array' => 'Las equivalencias deben enviarse como lista.',
            'external_codes.max' => 'Un procedimiento no puede declarar mas de 20 equivalencias.',
            'external_codes.*.system.required_with' => 'Cada equivalencia debe declarar su sistema (por ejemplo minsa_rm550_2023 o sis).',
            'external_codes.*.code.required_with' => 'Cada equivalencia debe declarar su codigo.',
            'external_codes.*.system.regex' => 'El sistema de la equivalencia solo admite minusculas, numeros, punto, guion y guion bajo.',
            'material_recipe.array' => 'La receta de insumos debe enviarse como lista.',
            'material_recipe.max' => 'Un procedimiento no puede declarar mas de 50 insumos.',
            'material_recipe.*.product_id.required_with' => 'Cada linea de la receta debe declarar su insumo.',
            'material_recipe.*.product_id.exists' => 'El insumo seleccionado no existe.',
            'material_recipe.*.product_id.distinct' => 'El insumo esta repetido en la receta.',
            'material_recipe.*.quantity.required_with' => 'Cada linea de la receta debe declarar su cantidad estimada.',
            'material_recipe.*.quantity.gt' => 'La cantidad estimada debe ser mayor a cero.',
        ];
    }

    /**
     * #293 S7: el par (sistema, codigo) es unico dentro del mismo payload.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $codes = $this->input('external_codes');

            if (! is_array($codes)) {
                return;
            }

            $seen = [];
            foreach ($codes as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $system = $row['system'] ?? null;
                $code = $row['code'] ?? null;

                if (! is_string($system) || ! is_string($code)) {
                    continue;
                }

                $key = $system.'|'.$code;
                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "external_codes.{$index}.code",
                        "El código {$code} ya está declarado para este procedimiento."
                    );
                }

                $seen[$key] = true;
            }
        });
    }
}
