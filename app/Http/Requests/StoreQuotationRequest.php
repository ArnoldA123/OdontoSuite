<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StoreQuotationRequest extends FormRequest
{
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
            'treatment_plan_id' => 'nullable|exists:treatment_plans,id',
            'patient_id' => 'required|exists:patients,id',
            'quotation_date' => 'nullable|date|before_or_equal:today',
            'valid_until' => 'nullable|date|after:quotation_date',
            'subtotal' => 'required|numeric|min:0|max:999999.99',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0|max:999999.99',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'terms_conditions' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:1000',
            'payment_terms' => 'nullable|array',
            'payment_terms.*' => 'string|max:200',
            'items' => 'nullable|array|max:50',
            'items.*.description' => 'required_with:items|string|max:200',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01|max:999.99',
            'items.*.unit_price' => 'required_with:items|numeric|min:0|max:99999.99'
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
            'treatment_plan_id.exists' => 'El plan de tratamiento seleccionado no existe',
            'subtotal.required' => 'El subtotal es obligatorio',
            'subtotal.min' => 'El subtotal no puede ser negativo',
            'subtotal.max' => 'El subtotal no puede exceder S/ 999,999.99',
            'discount_percentage.max' => 'El descuento no puede exceder 100%',
            'tax_percentage.max' => 'El impuesto no puede exceder 100%',
            'valid_until.after' => 'La fecha de vencimiento debe ser posterior a la fecha del presupuesto',
            'items.max' => 'No se pueden agregar más de 50 items',
            'items.*.description.required_with' => 'La descripción del item es obligatoria',
            'items.*.quantity.required_with' => 'La cantidad es obligatoria',
            'items.*.unit_price.required_with' => 'El precio unitario es obligatorio'
        ];
    }
}
