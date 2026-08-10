<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'document_number' => $this->document_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'age' => $this->birth_date ? (int) $this->birth_date->diffInYears(now()) : null,
            'gender' => $this->gender,
            'address' => $this->address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'medical_history' => $this->medical_history,
            'allergies' => $this->allergies,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Contadores condicionales
            'appointments_count' => $this->when(isset($this->appointments_count), $this->appointments_count),
            'treatment_plans_count' => $this->when(isset($this->treatment_plans_count), $this->treatment_plans_count),
            'quotations_count' => $this->when(isset($this->quotations_count), $this->quotations_count),
            'medical_records_count' => $this->when(isset($this->medical_records_count), $this->medical_records_count),
            
            // Relaciones condicionales
            'appointments' => $this->whenLoaded('appointments', function () {
                return $this->appointments->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'scheduled_at' => $appointment->scheduled_at?->toISOString(),
                        'status' => $appointment->status,
                        'appointment_type' => $appointment->appointmentType ? [
                            'id' => $appointment->appointmentType->id,
                            'name' => $appointment->appointmentType->name,
                        ] : null,
                    ];
                });
            }),
            'treatment_plans' => $this->whenLoaded('treatmentPlans', function () {
                return $this->treatmentPlans->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'title' => $plan->title,
                        'status' => $plan->status,
                        'created_at' => $plan->created_at?->toISOString(),
                    ];
                });
            }),
            'quotations' => $this->whenLoaded('quotations', function () {
                return $this->quotations->map(function ($quotation) {
                    return [
                        'id' => $quotation->id,
                        'quotation_date' => $quotation->quotation_date?->format('Y-m-d'),
                        'total' => $quotation->total,
                        'status' => $quotation->status,
                    ];
                });
            }),
            'medical_records' => $this->whenLoaded('medicalRecords', function () {
                return $this->medicalRecords->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'first_visit_date' => $record->first_visit_date?->format('Y-m-d'),
                        'chief_complaint' => $record->chief_complaint,
                    ];
                });
            }),
        ];
    }
}

