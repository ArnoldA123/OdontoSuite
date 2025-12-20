<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Appointment;
use App\Models\TreatmentPlan;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StoreTransactionRequest extends FormRequest
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
                'exists:patients,id'
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                'exists:appointments,id'
            ],
            'treatment_plan_id' => [
                'nullable',
                'integer',
                'exists:treatment_plans,id'
            ],
            'payment_method_id' => [
                'required',
                'integer',
                'exists:payment_methods,id'
            ],
            'type' => [
                'required',
                'in:payment,refund,discount,adjustment'
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01'
            ],
            'subtotal' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'discount_type' => [
                'nullable',
                'in:percentage,fixed'
            ],
            'discount_amount' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'discount_authorized_by' => [
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'description' => [
                'required',
                'string',
                'max:255'
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500'
            ],
            'reference_number' => [
                'nullable',
                'string',
                'max:100'
            ],
            'metadata' => [
                'nullable',
                'array'
            ]
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
            'payment_method_id.exists' => 'El método de pago seleccionado no existe.',
            'type.required' => 'El tipo de transacción es requerido.',
            'type.in' => 'El tipo de transacción debe ser payment, refund, discount o adjustment.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'discount_type.in' => 'El tipo de descuento debe ser porcentaje o fijo.',
            'discount_amount.numeric' => 'El monto de descuento debe ser un número.',
            'discount_amount.min' => 'El monto de descuento debe ser mayor o igual a 0.',
            'discount_authorized_by.exists' => 'El usuario autorizador no existe.',
            'description.required' => 'La descripción es requerida.',
            'description.max' => 'La descripción no puede exceder los 255 caracteres.',
            'notes.max' => 'Las notas no pueden exceder los 500 caracteres.',
            'reference_number.max' => 'El número de referencia no puede exceder los 100 caracteres.',
            'metadata.array' => 'Los metadatos deben ser un objeto.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if there's an active cash session
            $activeSession = CashRegisterSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->first();

            if (!$activeSession) {
                $validator->errors()->add(
                    'session',
                    'No hay una sesión de caja abierta. Debe abrir la caja antes de registrar transacciones.'
                );
            }

            // Validate discount authorization
            $discountAmount = $this->input('discount_amount', 0);
            $amount = $this->input('amount', 0);

            if ($discountAmount > 0 && $amount > 0) {
                $discountPercentage = ($discountAmount / $amount) * 100;

                if ($discountPercentage > 10 && !$this->input('discount_authorized_by')) {
                    $validator->errors()->add(
                        'discount_authorized_by',
                        'Los descuentos mayores al 10% requieren autorización del administrador.'
                    );
                }
            }

            // Los campos appointment_id y treatment_plan_id son opcionales
            // No se requiere validación adicional aquí

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

