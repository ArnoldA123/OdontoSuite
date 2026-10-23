<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;

/**
 * #287 (T4, S5/S7) — the bank deposit of the drawer.
 *
 * The deposit moves money out of the gaveta towards a bank account, so it needs
 * the bank reference and the moment the money was deposited. Whether the
 * session is open and whether the drawer holds that much cash are business
 * rules of `CashDepositService`: they depend on the session, not on the shape
 * of the payload.
 */
class StoreCashDepositRequest extends FormRequest
{
    use LocalizedErrors;

    public function authorize(): bool
    {
        // Authorization is the route's `permission:` middleware; this request
        // only owns the shape of the payload.
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cash_register_session_id' => ['required', 'integer', 'exists:cash_register_sessions,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['required', 'string', 'max:100'],
            'deposited_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_register_session_id.required' => 'La sesión de caja es requerida.',
            'cash_register_session_id.exists' => 'La sesión de caja seleccionada no existe.',
            'amount.required' => 'El monto del depósito es requerido.',
            'amount.numeric' => 'El monto del depósito debe ser numérico.',
            'amount.min' => 'El monto del depósito debe ser mayor que cero.',
            'reference.required' => 'La referencia bancaria del depósito es requerida.',
            'reference.string' => 'La referencia bancaria debe ser texto.',
            'reference.max' => 'La referencia bancaria no puede exceder 100 caracteres.',
            'deposited_at.date' => 'La fecha del depósito debe ser una fecha válida.',
            'deposited_at.before_or_equal' => 'La fecha del depósito no puede ser futura.',
            'notes.string' => 'Las notas deben ser texto.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
        ];
    }
}
