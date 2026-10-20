<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\HasCrudRules;
use App\Models\ProcedureCatalog;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    use HasCrudRules;

    /**
     * Rules shared by the store and update actions.
     */
    protected function storeRules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:specialties,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->queryWithUsageCounts();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('q')) {
            $this->applySearch($query, trim((string) $request->input('q')));
        }

        $specialties = $query->orderBy('name')->get();

        return response()->json([
            'data' => $specialties,
            'meta' => [
                'message' => 'Especialidades cargadas exitosamente',
                'total' => $specialties->count(),
                'kpis' => $this->kpis(),
            ],
        ]);
    }

    public function active(): JsonResponse
    {
        $specialties = Specialty::active()->orderBy('name')->get();

        return response()->json([
            'data' => $specialties,
            'meta' => [
                'message' => 'Especialidades activas cargadas exitosamente',
                'total' => $specialties->count(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $specialty = $this->queryWithUsageCounts()->findOrFail($id);

        return response()->json([
            'data' => $specialty,
            'meta' => ['message' => 'Especialidad obtenida exitosamente'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->storeRules());

        $specialty = Specialty::create($validated);

        return response()->json([
            'data' => $specialty,
            'meta' => ['message' => 'Especialidad creada exitosamente'],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $specialty = Specialty::findOrFail($id);

        $validated = $request->validate($this->updateRules($this->storeRules(), [], [
            'code' => "sometimes|string|max:50|unique:specialties,code,{$id}",
        ]));

        $specialty->update($validated);

        return response()->json([
            'data' => $specialty,
            'meta' => ['message' => 'Especialidad actualizada exitosamente'],
        ]);
    }

    /**
     * Base query for the admin surfaces: every response carries the usage
     * counts the interface renders, so a row never shows a fabricated zero.
     *
     * @return Builder<Specialty>
     */
    private function queryWithUsageCounts(): Builder
    {
        return Specialty::query()->withCount([
            'procedureCatalog as procedures_count',
            'users as professionals_count',
        ]);
    }

    /**
     * @param Builder<Specialty> $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        $query->where(function (Builder $nested) use ($term) {
            $nested->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * KPI population. It is computed over the whole catalog on purpose: the
     * page filters the list server-side, and totals must not shrink with it.
     *
     * @return array<string, int>
     */
    private function kpis(): array
    {
        return [
            'total' => Specialty::query()->count(),
            'active' => Specialty::query()->active()->count(),
            'inactive' => Specialty::query()->where('is_active', false)->count(),
            'procedures' => ProcedureCatalog::query()->whereNotNull('specialty_id')->count(),
        ];
    }
}
