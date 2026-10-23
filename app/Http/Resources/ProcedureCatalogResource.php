<?php

namespace App\Http\Resources;

use App\Models\ProcedureCatalogMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcedureCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'specialty' => $this->specialty?->code,
            'specialty_id' => $this->specialty_id,
            'specialty_name' => $this->specialty?->name,
            // #293 S1: sobre que aplica el procedimiento (none|tooth|surface|quadrant|arch).
            'site_type' => $this->site_type ?? 'none',
            // #293 S7: equivalencias con el nomenclador publico; siempre lista.
            'external_codes' => $this->externalCodes
                ->map(fn ($externalCode) => [
                    'system' => $externalCode->system,
                    'code' => $externalCode->code,
                    'description' => $externalCode->description,
                ])
                ->values()
                ->all(),
            // 'legacy_specialty' (Sprint 2 DM-7) marcado deprecated. Mantenido
            // para que consumidores existentes no rompan. Usar 'specialty' (FK).
            'legacy_specialty' => $this->legacy_specialty,
            'default_cost' => (float) $this->default_cost,
            'default_duration_minutes' => $this->default_duration_minutes,
            'requirements' => $this->requirements,
            'materials_needed' => $this->materials_needed,
            'materials_needed_list' => $this->materialsNeededList(),
            // #295 S4: recetario de insumos del procedimiento. Solo se publica
            // cuando la relacion viene cargada (detalle, alta y edicion); el
            // indice no paga una consulta por fila.
            'material_recipe' => $this->whenLoaded('materialRecipe', fn (): array => $this->materialRecipe
                ->map(fn (ProcedureCatalogMaterial $line): array => [
                    'product_id' => (int) $line->product_id,
                    'product_name' => $line->product?->name,
                    'unit' => $line->product?->unit,
                    'cost_price' => (float) ($line->product?->cost_price ?? 0),
                    'stock_quantity' => (float) ($line->product?->stock_quantity ?? 0),
                    'quantity' => (float) $line->quantity,
                ])
                ->values()
                ->all()),
            'requires_anesthesia' => (bool) $this->requires_anesthesia,
            'requires_radiographs' => (bool) $this->requires_radiographs,
            'steps' => $this->steps,
            'contraindications' => $this->contraindications,
            'post_procedure_care' => $this->post_procedure_care,
            'is_active' => (bool) $this->is_active,
            'is_favorite' => (bool) ($this->is_favorite ?? false),
            'favorite_position' => $this->favorite_position ?? null,
        ];
    }
}
