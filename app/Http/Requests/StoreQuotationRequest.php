<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * FormRequest for creating a Quotation.
 *
 * Slice 02 / T-02.5, T-02.7 — BF-009 fix + new optional fields:
 *  - procedure_id      → integer, nullable, exists:procedure_catalog,id
 *  - payment_method_id → integer, nullable, exists:payment_methods,id
 *  - patient_id is sometimes|nullable → no 'required' message.
 *
 * @see openspec/changes/bugfix-2026-08/specs/02-form-requests.md
 */
class StoreQuotationRequest extends FormRequest
{
    use LocalizedErrors;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()->role, ['administrador', 'finanzas', 'odontologo', 'implantologo']);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('terms_conditions')) {
            $this->merge([
                'terms_conditions' => Str::limit(strip_tags($this->terms_conditions), 2000),
            ]);
        }

        if ($this->has('notes')) {
            $this->merge([
                'notes' => Str::limit(strip_tags($this->notes), 1000),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'treatment_plan_id' => 'sometimes|nullable|exists:treatment_plans,id',
            'patient_id' => 'sometimes|nullable|exists:patients,id',
            'quotation_date' => 'sometimes|nullable|date|before_or_equal:today',
            'valid_until' => 'sometimes|nullable|date|after:quotation_date',
            'subtotal' => 'required|numeric|min:0|max:999999.99',
            'discount_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'discount_amount' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'tax_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'terms_conditions' => 'sometimes|nullable|string|max:2000',
            'notes' => 'sometimes|nullable|string|max:1000',
            'payment_terms' => 'sometimes|nullable|array',
            'payment_terms.*' => 'string|max:200',
            'items' => 'sometimes|nullable|array|max:50',
            'items.*.description' => 'required_with:items|string|max:200',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01|max:999.99',
            'items.*.unit_price' => 'required_with:items|numeric|min:0|max:99999.99',
            // Slice 02 / T-02.7 — optional procedure + payment method.
            'procedure_id' => 'nullable|exists:procedure_catalog,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * BF-009 fix: patient_id.message describes the optional behavior rather than
     * requiring it (the rule is sometimes|nullable, so 'required' was misleading).
     */
    public function messages(): array
    {
        return [
            'patient_id.exists' => 'El paciente seleccionado no existe',
            // BF-009 — no 'patient_id.required' message; rule is sometimes|nullable.
            'treatment_plan_id.exists' => 'El plan de tratamiento seleccionado no existe',
            'subtotal.required' => 'El subtotal es obligatorio',
            'subtotal.min' => 'El subtotal no puede ser negativo',
            'subtotal.max' => 'El subtotal no puede exceder S/ 999,999.99',
            'discount_percentage.max' => 'El descuento no puede exceder 100%',
            'tax_percentage.max' => 'El impuesto no puede exceder 100%',
            'valid_until.after' => 'La fecha de vencimiento debe ser posterior a la fecha del presupuesto',
            // Slice 02 / T-02.7 — es messages for new optional fields.
            'procedure_id.exists' => 'El procedimiento seleccionado no existe',
            'payment_method_id.exists' => 'El método de pago seleccionado no existe',
            'items.max' => 'No se pueden agregar más de 50 items',
            'items.*.description.required_with' => 'La descripción del item es obligatoria',
            'items.*.quantity.required_with' => 'La cantidad es obligatoria',
            'items.*.unit_price.required_with' => 'El precio unitario es obligatorio'
        ];
    }
}
