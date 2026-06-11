<?php

namespace App\Services;

use App\Models\ProcedureCatalog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProcedureCatalogService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = ProcedureCatalog::query()->with('specialty');

        if (!empty($filters['specialty'])) {
            $query->whereHas('specialty', fn ($q) => $q->where('code', $filters['specialty']));
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
            ->with('specialty')
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
                'specialty' => $p->specialty?->code,
                'specialty_name' => $p->specialty?->name,
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
            ->with('specialty')
            ->active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'specialty_id', 'default_cost', 'default_duration_minutes', 'materials_needed'])
            ->map(fn (ProcedureCatalog $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'specialty' => $p->specialty?->code,
                'specialty_name' => $p->specialty?->name,
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

    /**
     * Lista personalizada para un usuario clínico.
     * - Si pide ?specialty=code y el usuario tiene esa specialty -> filtra por specialty.
     * - Si NO pide specialty -> trae los de las specialties del usuario + favoritos (favoritos primero).
     * - Si el usuario no tiene specialties -> trae todos los activos.
     */
    public function forUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 100));

        $query = ProcedureCatalog::query()
            ->with('specialty')
            ->active();

        if (!empty($filters['specialty'])) {
            $userSpecialtyCodes = $user->specialties()->pluck('code')->all();
            if (in_array($filters['specialty'], $userSpecialtyCodes, true)) {
                $query->whereHas('specialty', fn ($q) => $q->where('code', $filters['specialty']));
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $userSpecialtyIds = $user->specialties()->pluck('specialties.id')->all();
            $favoriteIds = $user->favoriteProcedures()->pluck('procedure_catalog.id')->all();

            if (!empty($userSpecialtyIds)) {
                $query->where(function (Builder $q) use ($userSpecialtyIds, $favoriteIds) {
                    $q->whereIn('specialty_id', $userSpecialtyIds);
                    if (!empty($favoriteIds)) {
                        $q->orWhereIn('id', $favoriteIds);
                    }
                });
            } elseif (empty($favoriteIds)) {
                // sin specialties ni favoritos -> todos los activos
            }
        }

        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $favoriteIds = $user->favoriteProcedures()->pluck('procedure_catalog.id')->all();
        $favoritePositions = DB::table('user_favorite_procedures')
            ->where('user_id', $user->id)
            ->pluck('position', 'procedure_catalog_id')
            ->all();

        $items = $query->orderBy('name')->paginate($perPage);

        $items->getCollection()->transform(function (ProcedureCatalog $p) use ($favoriteIds, $favoritePositions) {
            $p->is_favorite = in_array($p->id, $favoriteIds, true);
            $p->favorite_position = $favoritePositions[$p->id] ?? null;
            return $p;
        });

        $items->getCollection()->sortBy([
            ['is_favorite', 'desc'],
            ['favorite_position', 'asc'],
            ['name', 'asc'],
        ])->values();

        return $items;
    }

    public function create(array $data): ProcedureCatalog
    {
        $procedure = ProcedureCatalog::create($this->normalize($data));

        $this->fireUpdatedEvent($procedure, 'created', $procedure->getAttributes());

        return $procedure;
    }

    public function update(ProcedureCatalog $procedure, array $data): ProcedureCatalog
    {
        $before = $procedure->only(['default_cost', 'name', 'code', 'is_active']);
        $procedure->update($this->normalize($data, isUpdate: true));
        $procedure->refresh();
        $after = $procedure->only(['default_cost', 'name', 'code', 'is_active']);

        $changed = array_diff_assoc($after, $before);
        if (!empty($changed)) {
            $this->fireUpdatedEvent($procedure, 'updated', $changed);
        }

        return $procedure;
    }

    public function deactivate(ProcedureCatalog $procedure): ProcedureCatalog
    {
        $procedure->is_active = false;
        $procedure->save();

        // Sprint 3 fix (IM-7): registra version antes de notificar.
        $this->fireUpdatedEvent($procedure, 'deactivated', ['is_active' => false]);

        // Sprint 3 fix (IM-4): notifica a los clinicos que tienen este
        // procedimiento como favorito y transmite por Reverb.
        try {
            $notifiedUsers = $procedure->favoritedBy()->get();
            event(new \App\Events\ProcedureCatalogDeactivated(
                $procedure,
                \Illuminate\Support\Facades\Auth::user(),
                $notifiedUsers,
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning(
                'Failed to fire ProcedureCatalogDeactivated event',
                ['procedure_id' => $procedure->id, 'error' => $e->getMessage()]
            );
        }

        return $procedure;
    }

    /**
     * Helper para disparar ProcedureCatalogUpdated envuelto en try/catch.
     */
    private function fireUpdatedEvent(ProcedureCatalog $procedure, string $changeType, array $changedFields): void
    {
        try {
            event(new \App\Events\ProcedureCatalogUpdated(
                $procedure,
                $changeType,
                $changedFields,
                \Illuminate\Support\Facades\Auth::user(),
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning(
                'Failed to fire ProcedureCatalogUpdated event',
                ['procedure_id' => $procedure->id, 'change_type' => $changeType, 'error' => $e->getMessage()]
            );
        }
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
            'specialty_id',
            'legacy_specialty',
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
