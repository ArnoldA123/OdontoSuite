<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Branch;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OpenCashRegisterRequest extends FormRequest
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
        if ($this->has('opening_notes')) {
            $this->merge([
                'opening_notes' => Str::limit(strip_tags($this->opening_notes), 500),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'integer',
                'exists:branches,id'
            ],
            'opening_amount' => [
                'required',
                'numeric',
                'min:0'
            ],
            'opening_notes' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'La sucursal es requerida.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'opening_amount.required' => 'El monto de apertura es requerido.',
            'opening_amount.numeric' => 'El monto de apertura debe ser un número.',
            'opening_amount.min' => 'El monto de apertura debe ser mayor o igual a 0.',
            'opening_notes.max' => 'Las notas de apertura no pueden exceder los 500 caracteres.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if there's already an open session for this user and branch
            $existingSession = CashRegisterSession::where('user_id', Auth::id())
                ->where('branch_id', $this->input('branch_id'))
                ->where('status', 'open')
                ->first();

            if ($existingSession) {
                $validator->errors()->add(
                    'session',
                    'Ya existe una sesión de caja abierta para esta sucursal.'
                );
            }
        });
    }
}

