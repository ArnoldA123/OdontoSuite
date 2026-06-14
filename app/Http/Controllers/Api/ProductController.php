<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints de lectura para el catalogo de productos/insumos.
 *
 * Por ahora expone solo `search` (autocomplete) usado por el wizard de
 * consulta para registrar materiales. El CRUD admin de productos es
 * un modulo aparte (futuro). Mantenemos este controller minimo para
 * evitar meter rutas con stubs vacios (pitfall #13 de la skill).
 */
class ProductController extends Controller
{
    /**
     * GET /api/products/search?q=...&limit=20
     *
     * Devuelve los primeros N productos activos que matchean el termino
     * de busqueda por nombre, codigo o codigo de barras. Usado por el
     * <input> de materiales en ConsultationWizard para no obligar al
     * usuario a tipear IDs.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 20);
        $limit = max(1, min($limit, 50));

        $query = Product::query()->where('is_active', true);

        if ($term !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            });
        }

        try {
            $items = $query
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name', 'code', 'unit', 'cost_price', 'sale_price', 'stock_quantity']);
        } catch (\Throwable $e) {
            Log::error('ProductController@search: ' . $e->getMessage());
            return response()->json(['message' => 'Error al buscar productos'], 500);
        }

        return response()->json([
            'data' => $items->map(fn (Product $p) => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'unit' => $p->unit,
                'cost_price' => (float) ($p->cost_price ?? 0),
                'sale_price' => (float) ($p->sale_price ?? 0),
                'stock_quantity' => (float) ($p->stock_quantity ?? 0),
            ])->values(),
            'meta' => [
                'query' => $term,
                'count' => $items->count(),
            ],
        ]);
    }
}
