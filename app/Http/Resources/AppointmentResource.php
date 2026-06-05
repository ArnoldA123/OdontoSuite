<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
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
            'user_id' => $this->user_id,
            'dental_chair_id' => $this->dental_chair_id,
            'appointment_type_id' => $this->appointment_type_id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'notes' => $this->notes,
            'treatment_notes' => $this->treatment_notes,
            'idempotency_key' => $this->idempotency_key,
            'has_payment' => $this->when(
                $this->relationLoaded('transactions'),
                fn() => $this->transactions()
                    ->where('type', 'payment')
                    ->where('status', '!=', 'voided')
                    ->exists()
            ),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relaciones condicionales
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'first_name' => $this->patient->first_name,
                    'last_name' => $this->patient->last_name,
                    'full_name' => $this->patient->full_name,
                    'email' => $this->patient->email,
                    'phone' => $this->patient->phone,
                    'document_number' => $this->patient->document_number,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'specialty' => $this->user->specialty,
                ];
            }),
            'dental_chair' => $this->whenLoaded('dentalChair', function () {
                return [
                    'id' => $this->dentalChair->id,
                    'name' => $this->dentalChair->name,
                    'code' => $this->dentalChair->code,
                ];
            }),
            'appointment_type' => $this->whenLoaded('appointmentType', function () {
                return [
                    'id' => $this->appointmentType->id,
                    'name' => $this->appointmentType->name,
                    'default_duration_minutes' => $this->appointmentType->default_duration_minutes,
                    'price' => $this->appointmentType->price,
                    'color' => $this->appointmentType->color,
                ];
            }),
            'created_by_user' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),
            'updated_by_user' => $this->whenLoaded('updatedBy', function () {
                return [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ];
            }),
            'recurrence' => $this->whenLoaded('recurrence', function () {
                return [
                    'id' => $this->recurrence->id,
                    'frequency' => $this->recurrence->frequency,
                    'interval_value' => $this->recurrence->interval_value,
                    'days_of_week' => $this->recurrence->days_of_week,
                    'day_of_month' => $this->recurrence->day_of_month,
                    'end_date' => $this->recurrence->end_date?->toISOString(),
                    'max_occurrences' => $this->recurrence->max_occurrences,
                ];
            }),
        ];
    }
}
