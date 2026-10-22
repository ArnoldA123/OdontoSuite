<?php

namespace App\Services;

use App\Models\ProcedureCatalog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProcedureCatalogService
{
    public function __construct(private readonly ProcedurePriceResolver $prices) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = ProcedureCatalog::query()->with(['specialty', 'externalCodes']);

        if (! empty($filters['specialty'])) {
            $query->whereHas('specialty', fn ($q) => $q->where('code', $filters['specialty']));
        }

        if (! empty($filters['site_type'])) {
            $query->where('site_type', $filters['site_type']);
        }

        // #293 S7: buscar el procedimiento desde su codigo publico. Sin
        // `external_system` el codigo se busca en cualquier nomenclador.
        $externalSystem = $filters['external_system'] ?? null;
        $externalCode = $filters['external_code'] ?? null;

        if (! empty($externalSystem) || ! empty($externalCode)) {
            $query->whereHas('externalCodes', function (Builder $q) use ($externalSystem, $externalCode) {
                if (! empty($externalSystem)) {
                    $q->where('system', $externalSystem);
                }

                if (! empty($externalCode)) {
                    $q->where('code', $externalCode);
                }
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['q'])) {
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
     * Resumen exacto del catálogo completo para los KPIs de la lista.
     * No depende de la página visible ni de los filtros activos.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $total = ProcedureCatalog::query()->count();
        $active = ProcedureCatalog::query()->where('is_active', true)->count();
        $avgDuration = ProcedureCatalog::query()->avg('default_duration_minutes');
        $avgCost = ProcedureCatalog::query()->avg('default_cost');

        $bySpecialty = DB::table('procedure_catalog')
            ->leftJoin('specialties', 'procedure_catalog.specialty_id', '=', 'specialties.id')
            ->select([
                'specialties.id as specialty_id',
                'specialties.code as specialty_code',
                'specialties.name as specialty_name',
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('specialties.id', 'specialties.code', 'specialties.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'specialty_id' => $row->specialty_id,
                'specialty_code' => $row->specialty_code,
                'specialty_name' => $row->specialty_name,
                'count' => (int) $row->count,
            ])
            ->all();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'avg_duration_minutes' => round((float) ($avgDuration ?? 0), 1),
            'avg_cost' => round((float) ($avgCost ?? 0), 2),
            'by_specialty' => $bySpecialty,
        ];
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

        $procedures = ProcedureCatalog::query()
            ->with('specialty')
            ->active()
            ->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $prices = $this->prices->resolveMany($procedures, $this->prices->currentBranchId());

        return $procedures
            ->map(fn (ProcedureCatalog $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'specialty' => $p->specialty?->code,
                'specialty_name' => $p->specialty?->name,
                // #290 S8: the picker answers the price of the caller's sede;
                // list_cost keeps the group agreement visible.
                'default_cost' => $prices[$p->id]['price'],
                'list_cost' => (float) $p->default_cost,
                'price_source' => $prices[$p->id]['source'],
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
        $procedures = ProcedureCatalog::query()
            ->with('specialty')
            ->active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'specialty_id', 'default_cost', 'default_duration_minutes', 'materials_needed']);

        $prices = $this->prices->resolveMany($procedures, $this->prices->currentBranchId());

        return $procedures
            ->map(fn (ProcedureCatalog $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'specialty' => $p->specialty?->code,
                'specialty_name' => $p->specialty?->name,
                'default_cost' => $prices[$p->id]['price'],
                'list_cost' => (float) $p->default_cost,
                'price_source' => $prices[$p->id]['source'],
                'default_duration_minutes' => $p->default_duration_minutes,
                'materials_needed' => $p->materials_needed,
                'label' => "{$p->code} — {$p->name}",
            ])
            ->all();
    }

    public function findOrFail(int $id): ProcedureCatalog
    {
        return ProcedureCatalog::with('externalCodes')->findOrFail($id);
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
            ->with(['specialty', 'externalCodes'])
            ->active();

        if (! empty($filters['specialty'])) {
            $userSpecialtyCodes = $user->specialties()->pluck('code')->all();
            if (in_array($filters['specialty'], $userSpecialtyCodes, true)) {
                $query->whereHas('specialty', fn ($q) => $q->where('code', $filters['specialty']));
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $userSpecialtyIds = $user->specialties()->pluck('specialties.id')->all();
            $favoriteIds = $user->favoriteProcedures()->pluck('procedure_catalog.id')->all();

            if (! empty($userSpecialtyIds)) {
                $query->where(function (Builder $q) use ($userSpecialtyIds, $favoriteIds) {
                    $q->whereIn('specialty_id', $userSpecialtyIds);
                    if (! empty($favoriteIds)) {
                        $q->orWhereIn('id', $favoriteIds);
                    }
                });
            } elseif (empty($favoriteIds)) {
                // sin specialties ni favoritos -> todos los activos
            }
        }

        if (! empty($filters['q'])) {
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
        $externalCodes = $data['external_codes'] ?? null;
        $replacesCodes = array_key_exists('external_codes', $data);

        $procedure = DB::transaction(function () use ($data, $externalCodes, $replacesCodes) {
            $procedure = ProcedureCatalog::create($this->normalize($data));

            if ($replacesCodes) {
                $this->syncExternalCodes($procedure, $externalCodes);
            }

            return $procedure;
        });

        $this->fireUpdatedEvent($procedure, 'created', $procedure->getAttributes());

        return $procedure;
    }

    public function update(ProcedureCatalog $procedure, array $data): ProcedureCatalog
    {
        $before = $procedure->only(['default_cost', 'name', 'code', 'is_active']);
        $replacesCodes = array_key_exists('external_codes', $data);
        $externalCodes = $data['external_codes'] ?? null;

        DB::transaction(function () use ($procedure, $data, $replacesCodes, $externalCodes) {
            $procedure->update($this->normalize($data, isUpdate: true));

            if ($replacesCodes) {
                $this->syncExternalCodes($procedure, $externalCodes);
            }
        });

        $procedure->refresh();
        $after = $procedure->only(['default_cost', 'name', 'code', 'is_active']);

        $changed = array_diff_assoc($after, $before);
        if (! empty($changed)) {
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
     * #293 S7: el alta y la edicion reemplazan el conjunto completo de
     * equivalencias. `null` o `[]` lo deja vacio.
     *
     * @param array<int, array<string, mixed>>|null $codes
     */
    private function syncExternalCodes(ProcedureCatalog $procedure, ?array $codes): void
    {
        $procedure->externalCodes()->delete();

        $rows = [];
        foreach ($codes ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'system' => $row['system'],
                'code' => $row['code'],
                'description' => $row['description'] ?? null,
            ];
        }

        if ($rows !== []) {
            $procedure->externalCodes()->createMany($rows);
        }

        $procedure->unsetRelation('externalCodes');
    }

    /**
     * @param  array<string, mixed> $data
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
            'site_type',
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

        if (! $isUpdate) {
            $normalized['is_active'] = $normalized['is_active'] ?? true;
            $normalized['default_cost'] = $normalized['default_cost'] ?? 0;
            $normalized['default_duration_minutes'] = $normalized['default_duration_minutes'] ?? 30;
            $normalized['site_type'] = $normalized['site_type'] ?? 'none';
        }

        if (array_key_exists('site_type', $normalized) && ! in_array($normalized['site_type'], ProcedureCatalog::SITE_TYPES, true)) {
            $normalized['site_type'] = 'none';
        }

        if (array_key_exists('steps', $normalized) && is_string($normalized['steps'])) {
            $decoded = json_decode($normalized['steps'], true);
            $normalized['steps'] = is_array($decoded) ? $decoded : null;
        }

        return $normalized;
    }
}
