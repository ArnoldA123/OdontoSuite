<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use App\Http\Requests\Concerns\SanitizesQuotationText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * FormRequest for updating a Quotation.
 *
 * Shares the free-text hygiene with StoreQuotationRequest (#204): the same
 * terms/notes input is stored in the same shape through both endpoints.
 */
class UpdateQuotationRequest extends FormRequest
{
    use LocalizedErrors, SanitizesQuotationText;

    public function authorize(): bool
    {
        return in_array(Auth::user()->role, ['administrador', 'finanzas', 'odontologo', 'implantologo']);
    }

    public function rules(): array
    {
        return [
            'quotation_date' => 'nullable|date',
            'valid_until' => 'nullable|date|after:quotation_date',
            'subtotal' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'terms_conditions' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:1000',
            'payment_terms' => 'nullable|array',
            'items' => 'nullable|array',
            'items.*.description' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|numeric|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'valid_until.after' => 'La fecha de vencimiento debe ser posterior a la fecha del presupuesto',
            'terms_conditions.max' => 'Las condiciones no pueden exceder 2000 caracteres',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres',
        ];
    }
}
