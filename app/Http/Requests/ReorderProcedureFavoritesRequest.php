<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProcedureFavoritesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:20',
            'ids.*' => 'integer|exists:procedure_catalog,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debes enviar al menos un ID de procedimiento.',
            'ids.array' => 'El formato de favoritos es invalido.',
            'ids.min' => 'Debes enviar al menos un ID.',
            'ids.max' => 'No puedes tener mas de 20 favoritos.',
            'ids.*.exists' => 'Uno o mas procedimientos no existen.',
        ];
    }
}
