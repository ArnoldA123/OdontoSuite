<?php

namespace App\Services;

use App\Models\ProcedureCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProcedureCatalogService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = ProcedureCatalog::query();

        if (!empty($filters['specialty'])) {
            $query->where('specialty', $filters['specialty']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['q'])) {
            $query->where(function (Builder $q) use ($filters) {
                $term = $filters['q'];
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 100));

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Búsqueda rápida para autocomplete. Devuelve máximo $limit resultados activos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term, int $limit = 15): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        return ProcedureCatalog::query()
            ->active()
            ->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (ProcedureCatalog $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'specialty' => $p->specialty,
                'default_cost' => (float) $p->default_cost,
                'default_duration_minutes' => $p->default_duration_minutes,
                'materials_needed' => $p->materials_needed,
                'materials_needed_list' => $p->materialsNeededList(),
                'requires_anesthesia' => (bool) $p->requires_anesthesia,
                'requires_radiographs' => (bool) $p->requires_radiographs,
                'label' => "{$p->code} — {$p->name}",
            ])
            ->all();
    }

    public function activeList(): array
    {
        return ProcedureCatalog::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'specialty', 'default_cost', 'default_duration_minutes', 'materials_needed'])
            ->map(fn (ProcedureCatalog $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'specialty' => $p->specialty,
                'default_cost' => (float) $p->default_cost,
                'default_duration_minutes' => $p->default_duration_minutes,
                'materials_needed' => $p->materials_needed,
                'label' => "{$p->code} — {$p->name}",
            ])
            ->all();
    }

    public function findOrFail(int $id): ProcedureCatalog
    {
        return ProcedureCatalog::findOrFail($id);
    }

    public function create(array $data): ProcedureCatalog
    {
        return ProcedureCatalog::create($this->normalize($data));
    }

    public function update(ProcedureCatalog $procedure, array $data): ProcedureCatalog
    {
        $procedure->update($this->normalize($data, isUpdate: true));
        return $procedure->refresh();
    }

    public function deactivate(ProcedureCatalog $procedure): ProcedureCatalog
    {
        $procedure->is_active = false;
        $procedure->save();
        return $procedure;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $isUpdate = false): array
    {
        $allowed = [
            'code',
            'name',
            'description',
            'specialty',
            'default_cost',
            'default_duration_minutes',
            'requirements',
            'materials_needed',
            'requires_anesthesia',
            'requires_radiographs',
            'steps',
            'contraindications',
            'post_procedure_care',
            'is_active',
        ];

        $normalized = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $normalized[$key] = $data[$key];
            }
        }

        if (!$isUpdate) {
            $normalized['is_active'] = $normalized['is_active'] ?? true;
            $normalized['default_cost'] = $normalized['default_cost'] ?? 0;
            $normalized['default_duration_minutes'] = $normalized['default_duration_minutes'] ?? 30;
        }

        if (array_key_exists('steps', $normalized) && is_string($normalized['steps'])) {
            $decoded = json_decode($normalized['steps'], true);
            $normalized['steps'] = is_array($decoded) ? $decoded : null;
        }

        return $normalized;
    }
}
