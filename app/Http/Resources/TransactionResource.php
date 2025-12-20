<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'appointment_id' => $this->appointment_id,
            'treatment_plan_id' => $this->treatment_plan_id,
            'payment_method_id' => $this->payment_method_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'subtotal' => $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_amount' => $this->discount_amount,
            'discount_authorized_by' => $this->discount_authorized_by,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'status' => $this->status,
            'description' => $this->description,
            'notes' => $this->notes,
            'reference_number' => $this->reference_number,
            'processed_at' => $this->processed_at?->toISOString(),
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relaciones condicionales
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'first_name' => $this->patient->first_name,
                    'last_name' => $this->patient->last_name,
                    'full_name' => $this->patient->full_name,
                ];
            }),
            'appointment' => $this->whenLoaded('appointment', function () {
                return [
                    'id' => $this->appointment->id,
                    'scheduled_at' => $this->appointment->scheduled_at?->toISOString(),
                    'status' => $this->appointment->status,
                ];
            }),
            'treatment_plan' => $this->whenLoaded('treatmentPlan', function () {
                return [
                    'id' => $this->treatmentPlan->id,
                    'title' => $this->treatmentPlan->title,
                    'status' => $this->treatmentPlan->status,
                ];
            }),
            'payment_method' => $this->whenLoaded('paymentMethod', function () {
                return [
                    'id' => $this->paymentMethod->id,
                    'name' => $this->paymentMethod->name,
                    'type' => $this->paymentMethod->type,
                ];
            }),
            'created_by_user' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),
        ];
    }
}

