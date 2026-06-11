<?php

namespace App\Services\Reports;

use App\Models\ProcedureCatalog;
use App\Models\TreatmentPlanItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 3 fix (IM-5): metricas de uso del catalogo de procedimientos.
 * Devuelve el top de procedimientos mas usados en planes de tratamiento
 * (periodo configurable) y el resumen por especialidad.
 */
class ProcedureStatsService
{
    public function getStats(array $filters = []): array
    {
        $startDate = $filters['from'] ?? Carbon::now()->subDays(90)->toDateString();
        $endDate = $filters['to'] ?? Carbon::now()->toDateString();

        $topProcedures = $this->topProcedures($startDate, $endDate, $filters['limit'] ?? 10);

        $bySpecialty = $this->bySpecialty($startDate, $endDate);

        $catalog = [
            'total' => ProcedureCatalog::count(),
            'active' => ProcedureCatalog::where('is_active', true)->count(),
            'inactive' => ProcedureCatalog::where('is_active', false)->count(),
        ];

        return [
            'period' => ['from' => $startDate, 'to' => $endDate],
            'catalog' => $catalog,
            'top_procedures' => $topProcedures,
            'by_specialty' => $bySpecialty,
        ];
    }

    private function topProcedures(string $startDate, string $endDate, int $limit): array
    {
        return TreatmentPlanItem::query()
            ->select([
                'procedure_catalog_id',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('SUM(unit_cost * quantity) as total_revenue'),
                DB::raw('SUM(quantity) as total_quantity'),
            ])
            ->whereNotNull('procedure_catalog_id')
            ->whereHas('treatmentPlan', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->with('procedureCatalog:id,code,name,specialty_id')
            ->groupBy('procedure_catalog_id')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'procedure_id' => $item->procedure_catalog_id,
                    'code' => $item->procedureCatalog?->code,
                    'name' => $item->procedureCatalog?->name,
                    'usage_count' => (int) $item->usage_count,
                    'total_quantity' => (float) $item->total_quantity,
                    'total_revenue' => round((float) $item->total_revenue, 2),
                ];
            })
            ->toArray();
    }

    private function bySpecialty(string $startDate, string $endDate): array
    {
        return DB::table('treatment_plan_items')
            ->join('treatment_plans', 'treatment_plan_items.treatment_plan_id', '=', 'treatment_plans.id')
            ->join('procedure_catalog', 'treatment_plan_items.procedure_catalog_id', '=', 'procedure_catalog.id')
            ->leftJoin('specialties', 'procedure_catalog.specialty_id', '=', 'specialties.id')
            ->whereBetween('treatment_plans.created_at', [$startDate, $endDate])
            ->whereNotNull('treatment_plan_items.procedure_catalog_id')
            ->groupBy('specialties.id', 'specialties.code', 'specialties.name')
            ->select(
                'specialties.id as specialty_id',
                'specialties.code as specialty_code',
                'specialties.name as specialty_name',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('SUM(treatment_plan_items.unit_cost * treatment_plan_items.quantity) as total_revenue'),
            )
            ->orderByDesc('usage_count')
            ->get()
            ->map(function ($row) {
                return [
                    'specialty_id' => $row->specialty_id,
                    'specialty_code' => $row->specialty_code,
                    'specialty_name' => $row->specialty_name,
                    'usage_count' => (int) $row->usage_count,
                    'total_revenue' => round((float) $row->total_revenue, 2),
                ];
            })
            ->toArray();
    }
}
