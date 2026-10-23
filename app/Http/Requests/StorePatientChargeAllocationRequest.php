<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;

/**
 * #321 (S1) — imputing money already collected to the charges it paid.
 *
 * The shape belongs to this request; the two sum rules —the payment amount and
 * the charge amount— live in `PatientChargeAllocationService`, because they
 * depend on what the ledger already holds, not on the payload.
 */
class StorePatientChargeAllocationRequest extends FormRequest
{
    use LocalizedErrors;

    public function authorize(): bool
    {
        // Authorization is the route's `permission:` middleware; this request
        // only owns the shape of the payload.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.patient_charge_id' => [
                'required',
                'integer',
                'distinct',
                'exists:patient_charges,id',
            ],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allocations.required' => 'Debe indicar al menos un cargo a imputar.',
            'allocations.array' => 'Las imputaciones deben ser una lista.',
            'allocations.min' => 'Debe indicar al menos un cargo a imputar.',
            'allocations.*.patient_charge_id.required' => 'El cargo es requerido.',
            'allocations.*.patient_charge_id.integer' => 'El cargo debe ser un identificador válido.',
            'allocations.*.patient_charge_id.distinct' => 'El cargo está repetido en la imputación.',
            'allocations.*.patient_charge_id.exists' => 'El cargo seleccionado no existe.',
            'allocations.*.amount.required' => 'El importe de la imputación es requerido.',
            'allocations.*.amount.numeric' => 'El importe de la imputación debe ser numérico.',
            'allocations.*.amount.min' => 'El importe de la imputación debe ser mayor que cero.',
        ];
    }
}
