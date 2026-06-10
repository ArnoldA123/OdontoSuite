<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderProcedureFavoritesRequest;
use App\Models\ProcedureCatalog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcedureCatalogFavoriteController extends Controller
{
    public function store(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $procedure = ProcedureCatalog::active()->findOrFail($id);

            $position = (int) DB::table('user_favorite_procedures')
                ->where('user_id', $user->id)
                ->max('position');

            $user->favoriteProcedures()->syncWithoutDetaching([
                $procedure->id => ['position' => $position + 1],
            ]);

            return response()->json([
                'data' => $this->loadFavorite($user, $procedure->id),
                'meta' => ['message' => 'Procedimiento marcado como favorito'],
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Procedimiento no encontrado o inactivo'], 404);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogFavoriteController@store: ' . $e->getMessage());
            return response()->json(['message' => 'Error al marcar favorito'], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $user->favoriteProcedures()->detach($id);

            return response()->json([
                'meta' => ['message' => 'Favorito eliminado'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogFavoriteController@destroy: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar favorito'], 500);
        }
    }

    public function reorder(ReorderProcedureFavoritesRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];

        $user = Auth::user();
        DB::transaction(function () use ($user, $ids) {
            foreach ($ids as $position => $procedureId) {
                DB::table('user_favorite_procedures')
                    ->where('user_id', $user->id)
                    ->where('procedure_catalog_id', $procedureId)
                    ->update(['position' => $position + 1]);
            }
        });

        return response()->json([
            'data' => $this->loadAllFavorites($user),
            'meta' => ['message' => 'Favoritos reordenados'],
        ]);
    }

    public function index(): JsonResponse
    {
        $user = Auth::user();
        return response()->json([
            'data' => $this->loadAllFavorites($user),
            'meta' => ['total' => $user->favoriteProcedures()->count()],
        ]);
    }

    private function loadFavorite(User $user, int $procedureId): ?ProcedureCatalog
    {
        return $user->favoriteProcedures()
            ->where('procedure_catalog.id', $procedureId)
            ->with('specialty')
            ->first();
    }

    private function loadAllFavorites(User $user)
    {
        return $user->favoriteProcedures()
            ->with('specialty')
            ->get()
            ->map(fn (ProcedureCatalog $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'specialty' => $p->specialty?->code,
                'specialty_name' => $p->specialty?->name,
                'default_cost' => (float) $p->default_cost,
                'default_duration_minutes' => $p->default_duration_minutes,
                'position' => (int) $p->pivot->position,
            ]);
    }
}
