<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'phone' => $this->phone,
            'specialty' => $this->specialty,
            // bugfix-2026-08 slice 05: `specialties` is sourced from the
            // user_specialties pivot (source-of-truth per ADR-0007), NOT from
            // the dropped JSON column. whenLoaded() avoids accidental N+1.
            'specialties' => $this->whenLoaded('specialties', function () {
                return $this->specialties->map(fn ($s) => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'name' => $s->name,
                    'is_primary' => (bool) ($s->pivot->is_primary ?? false),
                ])->values();
            }),
            'is_active' => $this->is_active,
            'branch_id' => $this->branch_id,
            'professional_license' => $this->professional_license,
            'commission_rate' => $this->commission_rate,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relaciones condicionales
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                    'address' => $this->branch->address,
                ];
            }),
            'appointments_count' => $this->when(isset($this->appointments_count), $this->appointments_count),
        ];
    }
}

