<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * F3 (#292) — the correction note.
 *
 * A history or an evolution is never rewritten: a professional adds a dated,
 * reasoned note. `reason` is the motive the reader sees next to the original and
 * `content` is the correction itself; both are required and sanitized the same
 * way the evolution texts are (no HTML, length capped to the column).
 */
class StoreAddendumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()->role, ['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente']);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $textFields = [
            'reason' => 255,
            'content' => 5000,
        ];

        foreach ($textFields as $field => $maxLength) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([
                    $field => Str::limit(strip_tags($value), $maxLength),
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
            'reason' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo de la corrección es obligatorio',
            'reason.max' => 'El motivo de la corrección no puede exceder 255 caracteres',
            'content.required' => 'El contenido de la corrección es obligatorio',
            'content.max' => 'El contenido de la corrección no puede exceder 5000 caracteres',
        ];
    }
}
