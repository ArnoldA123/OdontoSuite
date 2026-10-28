<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use App\Models\CashRegisterSession;
use App\Support\MoneyVocabulary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * FormRequest for creating a Transaction.
 *
 * Slice 02 / T-02.8 — API-045 fix: payment_method_id is required and validated
 * via exists:payment_methods,id. (The frontend's PaymentModal already supplies
 * this value; the field was also declared in the original FormRequest, but
 * the slice explicitly re-affirms the rule and provides a clear es message.)
 *
 * #287, D4/D7/D8 — the rules follow the money vocabulary: the type decides
 * whether a method is required or prohibited, whether a discount belongs to
 * the row, whether the drawer has to be open and whether an administrator must
 * be registered behind it. `discount` is not a writable type anymore.
 *
 * @see openspec/changes/bugfix-2026-08/specs/02-form-requests.md
 */
class StoreTransactionRequest extends FormRequest
{
    use LocalizedErrors;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()->role, ['administrador', 'finanzas', 'recepcionista']);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('description')) {
            $this->merge([
                'description' => Str::limit(strip_tags($this->description), 255),
            ]);
        }

        if ($this->has('notes')) {
            $this->merge([
                'notes' => Str::limit(strip_tags($this->notes), 500),
            ]);
        }

        if ($this->has('reference_number')) {
            $this->merge([
                'reference_number' => Str::limit(strip_tags($this->reference_number), 100),
            ]);
        }

        if ($this->has('authorization_reason')) {
            $this->merge([
                'authorization_reason' => Str::limit(
                    strip_tags((string) $this->authorization_reason),
                    255,
                    ''
                ),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'patient_id' => [
                'required',
                'integer',
                'exists:patients,id',
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                'exists:appointments,id',
            ],
            'treatment_plan_id' => [
                'nullable',
                'integer',
                'exists:treatment_plans,id',
            ],
            'payment_method_id' => [
                'required_if:type,'.implode(',', [...MoneyVocabulary::CASH_IN, ...MoneyVocabulary::CASH_OUT]),
                'prohibited_unless:type,'.implode(',', [...MoneyVocabulary::CASH_IN, ...MoneyVocabulary::CASH_OUT]),
                'integer',
                'exists:payment_methods,id',
            ],
            'type' => [
                'required',
                'in:'.implode(',', MoneyVocabulary::CREATABLE),
            ],
            'amount' => [
                'required',
                'numeric',
                // #287, D3: the adjustment is signed, so only zero is refused;
                // every other type is an amount of money greater than zero.
                Rule::when(
                    $this->input('type') === MoneyVocabulary::ADJUSTMENT,
                    ['not_in:0'],
                    ['min:0.01'],
                ),
            ],
            'authorized_by' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'authorization_reason' => [
                'nullable',
                'required_if:type,'.implode(',', MoneyVocabulary::REQUIRES_AUTHORIZATION),
                'string',
                'max:255',
            ],
            'parent_transaction_id' => [
                'nullable',
                'integer',
                'exists:transactions,id',
                'prohibited_unless:type,'.MoneyVocabulary::REFUND,
            ],
            'subtotal' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'discount_type' => [
                'nullable',
                'in:percentage,fixed',
                'prohibited_unless:type,'.MoneyVocabulary::PAYMENT,
            ],
            'discount_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'lte:amount',
                'prohibited_unless:type,'.MoneyVocabulary::PAYMENT,
            ],
            'discount_authorized_by' => [
                'nullable',
                'integer',
                'exists:users,id',
                'prohibited_unless:type,'.MoneyVocabulary::PAYMENT,
            ],
            'description' => [
                'required',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
            // #410 (S9) — the override the accounting lock asks for travels with
            // the retry; the guard validates its length and the permission.
            'lock_override_reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'reference_number' => [
                'nullable',
                'string',
                'max:100',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            // #321 (S1): only a payment settles charges, and the sums are
            // checked by PatientChargeAllocationService against the ledger.
            'allocations' => [
                'nullable',
                'array',
                'min:1',
                'prohibited_unless:type,'.MoneyVocabulary::PAYMENT,
            ],
            'allocations.*.patient_charge_id' => [
                'required',
                'integer',
                'distinct',
                'exists:patient_charges,id',
            ],
            'allocations.*.amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'El paciente es requerido.',
            'patient_id.exists' => 'El paciente seleccionado no existe.',
            'appointment_id.exists' => 'La cita seleccionada no existe.',
            'treatment_plan_id.exists' => 'El plan de tratamiento seleccionado no existe.',
            'payment_method_id.required' => 'El método de pago es requerido.',
            'payment_method_id.required_if' => 'El método de pago es requerido para un pago o una devolución.',
            'payment_method_id.prohibited_unless' => 'Sólo el pago y la devolución llevan método de pago.',
            'payment_method_id.exists' => 'El método de pago seleccionado no existe.',
            'type.required' => 'El tipo de transacción es requerido.',
            'type.in' => 'El tipo de transacción debe ser payment, refund, write_off o adjustment.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'amount.not_in' => 'El ajuste no puede ser cero.',
            'authorized_by.exists' => 'El usuario autorizador no existe.',
            'authorization_reason.required_if' => 'El motivo de la autorización es requerido.',
            'authorization_reason.max' => 'El motivo de la autorización no puede exceder los 255 caracteres.',
            'parent_transaction_id.exists' => 'El pago de origen no existe.',
            'parent_transaction_id.prohibited_unless' => 'Sólo una devolución puede referirse a un pago de origen.',
            'discount_type.in' => 'El tipo de descuento debe ser porcentaje o fijo.',
            'discount_type.prohibited_unless' => 'El descuento es un campo del cobro: no aplica a este tipo de transacción.',
            'discount_amount.numeric' => 'El monto de descuento debe ser un número.',
            'discount_amount.min' => 'El monto de descuento debe ser mayor o igual a 0.',
            'discount_amount.prohibited_unless' => 'El descuento es un campo del cobro: no aplica a este tipo de transacción.',
            'discount_authorized_by.exists' => 'El usuario autorizador no existe.',
            'discount_authorized_by.prohibited_unless' => 'El descuento es un campo del cobro: no aplica a este tipo de transacción.',
            'description.required' => 'La descripción es requerida.',
            'description.max' => 'La descripción no puede exceder los 255 caracteres.',
            'notes.max' => 'Las notas no pueden exceder los 500 caracteres.',
            'reference_number.max' => 'El número de referencia no puede exceder los 100 caracteres.',
            'metadata.array' => 'Los metadatos deben ser un objeto.',
            'allocations.array' => 'Las imputaciones deben ser una lista.',
            'allocations.min' => 'Debe indicar al menos un cargo a imputar.',
            'allocations.prohibited_unless' => 'Sólo un pago puede imputarse a los cargos del paciente.',
            'allocations.*.patient_charge_id.required' => 'El cargo es requerido.',
            'allocations.*.patient_charge_id.integer' => 'El cargo debe ser un identificador válido.',
            'allocations.*.patient_charge_id.distinct' => 'El cargo está repetido en la imputación.',
            'allocations.*.patient_charge_id.exists' => 'El cargo seleccionado no existe.',
            'allocations.*.amount.required' => 'El importe de la imputación es requerido.',
            'allocations.*.amount.numeric' => 'El importe de la imputación debe ser numérico.',
            'allocations.*.amount.min' => 'El importe de la imputación debe ser mayor que cero.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = (string) $this->input('type');

            // #287, D7: only a payment and its refund need the drawer open. A
            // write-off or an adjustment is a ledger entry and cannot demand a
            // session it never writes to.
            if (MoneyVocabulary::movesCash($type)) {
                $activeSession = CashRegisterSession::where('user_id', Auth::id())
                    ->where('status', 'open')
                    ->first();

                if (! $activeSession) {
                    $validator->errors()->add(
                        'session',
                        'No hay una sesión de caja abierta. Debe abrir la caja antes de registrar transacciones.'
                    );
                }
            }

            // The discount is a field of the charge, so only a payment has one.
            if ($type !== MoneyVocabulary::PAYMENT) {
                return;
            }

            $discountAmount = (float) $this->input('discount_amount', 0);
            $amount = (float) $this->input('amount', 0);

            // Validate discount authorization
            if ($discountAmount > 0 && $amount > 0) {
                $discountPercentage = ($discountAmount / $amount) * 100;

                if ($discountPercentage > 10 && ! $this->input('discount_authorized_by')) {
                    $validator->errors()->add(
                        'discount_authorized_by',
                        'Los descuentos mayores al 10% requieren autorización del administrador.'
                    );
                }
            }

            // Validate that discount doesn't exceed amount
            if ($discountAmount > $amount) {
                $validator->errors()->add(
                    'discount_amount',
                    'El descuento no puede ser mayor al monto de la transacción.'
                );
            }
        });
    }
}
