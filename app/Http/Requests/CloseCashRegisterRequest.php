<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CloseCashRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()->role, ['administrador', 'finanzas']);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('closing_notes')) {
            $this->merge([
                'closing_notes' => Str::limit(strip_tags($this->closing_notes), 500),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'session_id' => [
                'required',
                'integer',
                'exists:cash_register_sessions,id'
            ],
            'closing_amount' => [
                'required',
                'numeric',
                'min:0'
            ],
            'closing_notes' => [
                'nullable',
                'string',
                'max:500'
            ],
            'arqueo' => [
                'nullable',
                'array'
            ],
            'arqueo.efectivo' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'arqueo.tarjeta_debito' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'arqueo.tarjeta_credito' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'arqueo.transferencia' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'arqueo.otros' => [
                'nullable',
                'numeric',
                'min:0'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'session_id.required' => 'El ID de la sesión es requerido.',
            'session_id.exists' => 'La sesión de caja no existe.',
            'closing_amount.required' => 'El monto de cierre es requerido.',
            'closing_amount.numeric' => 'El monto de cierre debe ser un número.',
            'closing_amount.min' => 'El monto de cierre debe ser mayor o igual a 0.',
            'closing_notes.max' => 'Las notas de cierre no pueden exceder los 500 caracteres.',
            'arqueo.array' => 'Los datos de arqueo deben ser un objeto.',
            'arqueo.*.numeric' => 'Los valores de arqueo deben ser números.',
            'arqueo.*.min' => 'Los valores de arqueo deben ser mayores o iguales a 0.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sessionId = $this->input('session_id');

            if ($sessionId) {
                $session = CashRegisterSession::find($sessionId);

                if ($session) {
                    // Check if session is open
                    if ($session->status !== 'open') {
                        $validator->errors()->add(
                            'session',
                            'La sesión de caja no está abierta.'
                        );
                    }
                }
            }
        });
    }
}

