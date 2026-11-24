<?php

namespace App\Http\Resources;

use App\Models\EndodonticsRecord;
use App\Models\ImplantologyRecord;
use App\Models\OralSurgeryRecord;
use App\Models\OrthodonticsRecord;
use App\Models\RehabilitationRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slice 1 of «Registros de especialidad integrados a la atención y la historia».
 *
 * One legible line per specialty record inside the act. The five tables keep
 * their own columns, so this projection is the single place that decides which
 * of them is the readable summary, which one is the date of the act and which
 * one is the status column. The raw clinical fields stay in the specialty
 * module; here they are a summary next to the rest of the encounter.
 *
 * @mixin Model
 */
class SpecialtyRecordSummaryResource extends JsonResource
{
    /**
     * @var array<class-string, array{type: string, label: string, date: string, date_label: string, status: ?string}>
     */
    private const TYPES = [
        ImplantologyRecord::class => [
            'type' => 'implantologia',
            'label' => 'Implantología',
            'date' => 'placement_date',
            'date_label' => 'Colocación',
            'status' => 'status',
        ],
        OrthodonticsRecord::class => [
            'type' => 'ortodoncia',
            'label' => 'Ortodoncia',
            'date' => 'treatment_start_date',
            'date_label' => 'Inicio',
            'status' => 'treatment_phase',
        ],
        EndodonticsRecord::class => [
            'type' => 'endodoncia',
            'label' => 'Endodoncia',
            'date' => 'treatment_completion_date',
            'date_label' => 'Cierre',
            'status' => 'treatment_status',
        ],
        RehabilitationRecord::class => [
            'type' => 'rehabilitacion',
            'label' => 'Rehabilitación',
            'date' => 'delivery_date',
            'date_label' => 'Entrega',
            'status' => 'status',
        ],
        OralSurgeryRecord::class => [
            'type' => 'cirugia_oral',
            'label' => 'Cirugía oral',
            'date' => 'surgery_date',
            'date_label' => 'Cirugía',
            'status' => 'status',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $record = $this->resource;
        $meta = self::TYPES[$record::class] ?? null;

        return [
            'type' => $meta['type'] ?? null,
            'type_label' => $meta['label'] ?? null,
            'id' => $record->getKey(),
            'patient_id' => $record->patient_id,
            'clinical_encounter_id' => $record->clinical_encounter_id,
            'treatment_plan_item_id' => $record->treatment_plan_item_id,
            'procedure' => $this->procedure(),
            'summary' => $this->summary($record, $meta['type'] ?? null),
            'date' => $this->date($record, $meta),
            'date_label' => $meta['date_label'] ?? null,
            'status' => $meta['status'] !== null ? $record->{$meta['status']} : null,
            'dental_piece' => $record->relationLoaded('dentalPiece')
                ? $record->dentalPiece?->fdi_number
                : null,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function procedure(): ?array
    {
        if (! $this->resource->relationLoaded('procedure') || $this->resource->procedure === null) {
            return null;
        }

        return [
            'id' => $this->resource->procedure->id,
            'code' => $this->resource->procedure->code,
            'name' => $this->resource->procedure->name,
        ];
    }

    /**
     * @param array{date: string, date_label: string}|null $meta
     */
    private function date(Model $record, ?array $meta): ?string
    {
        $value = $meta !== null ? $record->{$meta['date']} : null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        // A pending endodontic close or a prosthesis still in the laboratory has
        // no date of its own yet; the record's creation day is the honest one.
        return $record->created_at?->toDateString();
    }

    private function summary(Model $record, ?string $type): string
    {
        $piece = $record->relationLoaded('dentalPiece') && $record->dentalPiece !== null
            ? '#'.$record->dentalPiece->fdi_number
            : null;

        $parts = match ($type) {
            'implantologia' => [
                $record->implant_brand,
                $record->implant_model,
                $this->measure($record),
                $record->batch_number !== null ? "Lote {$record->batch_number}" : null,
            ],
            'ortodoncia' => [
                $record->treatment_type,
                $record->appliance_type,
            ],
            'endodoncia' => [
                $record->tooth_number !== null ? "Pieza {$record->tooth_number}" : null,
                $record->canal_count !== null
                    ? $record->canal_count.' '.($record->canal_count === 1 ? 'conducto' : 'conductos')
                    : null,
            ],
            'rehabilitacion' => [
                $record->prosthesis_type,
                $record->material_type,
                $record->laboratory_name,
            ],
            'cirugia_oral' => [
                $record->procedure_type,
                $record->surgery_site !== null ? "Sitio {$record->surgery_site}" : null,
            ],
            default => [],
        };

        return collect([$piece, ...$parts])
            ->filter(fn ($part): bool => is_string($part) && trim($part) !== '')
            ->map(fn (string $part): string => trim($part))
            ->implode(' · ');
    }

    private function measure(Model $record): ?string
    {
        if ($record->implant_diameter === null && $record->implant_length === null) {
            return null;
        }

        return trim(($record->implant_diameter ?? '—').' × '.($record->implant_length ?? '—').' mm');
    }
}
