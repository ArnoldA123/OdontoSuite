<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LocalizedErrors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * FormRequest for creating a CashMovement.
 *
 * Slice 02 / T-02.9, T-02.11 — the original CashMovementController had inline
 * validation with NO concept whitelist and no branch_id. This FormRequest
 * centralizes both:
 *  - branch_id → nullable, exists:branches,id
 *  - concept   → required, in:opening_balance,sale_refund,withdrawal,deposit,adjustment
 *
 * @see openspec/changes/bugfix-2026-08/specs/02-form-requests.md
 */
class StoreCashMovementRequest extends FormRequest
{
    use LocalizedErrors;

    /**
     * Authorization: only finanzas / administrador may record cash movements.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }
        return in_array($user->role, ['administrador', 'finanzas'], true);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('description')) {
            $this->merge([
                'description' => Str::limit(strip_tags((string) $this->description), 255),
            ]);
        }

        if ($this->has('notes')) {
            $this->merge([
                'notes' => Str::limit(strip_tags((string) $this->notes), 500),
            ]);
        }

        if ($this->has('reference')) {
            $this->merge([
                'reference' => Str::limit(strip_tags((string) $this->reference), 100),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'cash_register_session_id' => 'required|exists:cash_register_sessions,id',
            // Slice 02 / T-02.11 — whitelist movement concepts (downstream
            // reports depend on a known catalog).
            'concept' => 'required|in:opening_balance,sale_refund,withdrawal,deposit,adjustment',
            'type' => 'required|in:income,expense,withdrawal,deposit,adjustment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
            'reference' => 'nullable|string|max:100',
            'transaction_id' => 'nullable|exists:transactions,id',
            // Slice 02 / T-02.9 — optional branch association.
            'branch_id' => 'nullable|exists:branches,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cash_register_session_id.required' => 'La sesión de caja es requerida.',
            'cash_register_session_id.exists' => 'La sesión de caja seleccionada no existe.',
            'concept.required' => 'El concepto del movimiento es requerido.',
            'concept.in' => 'El concepto debe ser uno de: opening_balance, sale_refund, withdrawal, deposit, adjustment.',
            'type.required' => 'El tipo de movimiento es requerido.',
            'type.in' => 'El tipo debe ser income, expense, withdrawal, deposit o adjustment.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor a 0 (mínimo 0.01).',
            'description.required' => 'La descripción es requerida.',
            'description.max' => 'La descripción no puede exceder 255 caracteres.',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres.',
            'reference.max' => 'La referencia no puede exceder 100 caracteres.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }
}
