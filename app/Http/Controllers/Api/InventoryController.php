<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Exceptions\Inventory\InventoryException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Support\DateFilter;
use App\Support\PerPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Superficie del inventario (#295 S6).
 *
 * `products.stock_quantity` es la única verdad del stock actual y
 * `stock_movements` es el libro. Las cuatro lecturas publican el almacén tal
 * como está; las dos escrituras —recepción por compra y ajuste por conteo—
 * mueven el saldo y dejan su movimiento, siempre dentro de una transacción.
 *
 * La tira de KPIs y las alertas miden la tabla completa a propósito: un filtro
 * acota la página que se está mirando, no el almacén.
 */
class InventoryController extends Controller
{
    private const MOVEMENT_TYPES = ['in', 'out', 'adjustment', 'transfer', 'expired', 'damaged'];

    private const STOCK_STATUSES = ['low', 'out', 'expiring'];

    private const DETAIL_MOVEMENTS = 20;

    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * GET /api/inventory/products.
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'stock_status' => ['sometimes', 'nullable', Rule::in(self::STOCK_STATUSES)],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'numeric'],
        ]);

        try {
            $query = Product::query()->with('category:id,name');

            $this->applyProductFilters($query, $request);

            $page = $query->orderBy('name')->paginate(PerPage::resolve($request->get('per_page')));

            return response()->json([
                'data' => $page->getCollection()
                    ->map(fn (Product $product) => $this->productRow($product))
                    ->all(),
                'meta' => [
                    'total' => $page->total(),
                    'per_page' => $page->perPage(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'kpis' => $this->kpis(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('InventoryController@products: '.$e->getMessage());

            return response()->json(['message' => 'Error al listar los productos del inventario'], 500);
        }
    }

    /**
     * GET /api/inventory/products/{product}.
     */
    public function showProduct(Product $product): JsonResponse
    {
        try {
            $product->load('category:id,name');

            $movements = $product->stockMovements()
                ->with('createdBy:id,name')
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->limit(self::DETAIL_MOVEMENTS)
                ->get()
                ->map(fn (StockMovement $movement) => $this->movementTraceRow($movement))
                ->all();

            return response()->json([
                'data' => array_merge($this->productRow($product), ['movements' => $movements]),
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('InventoryController@showProduct: '.$e->getMessage());

            return response()->json(['message' => 'Error al obtener el producto del inventario'], 500);
        }
    }

    /**
     * GET /api/inventory/movements.
     */
    public function movements(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['sometimes', 'integer', 'min:1'],
            'type' => ['sometimes', 'nullable', Rule::in(self::MOVEMENT_TYPES)],
            'per_page' => ['sometimes', 'nullable', 'numeric'],
        ]);

        try {
            $query = StockMovement::query()->with(['product:id,name,code,unit', 'createdBy:id,name']);

            if ($productId = $request->integer('product_id')) {
                $query->where('product_id', $productId);
            }

            $type = $request->get('type');

            if (in_array($type, self::MOVEMENT_TYPES, true)) {
                $query->where('type', $type);
            }

            $from = DateFilter::normalize($request->get('from'));
            $to = DateFilter::normalize($request->get('to'));

            if ($from !== null) {
                $query->whereDate('movement_date', '>=', $from);
            }

            if ($to !== null) {
                $query->whereDate('movement_date', '<=', $to);
            }

            $page = $query
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->paginate(PerPage::resolve($request->get('per_page')));

            return response()->json([
                'data' => $page->getCollection()
                    ->map(fn (StockMovement $movement) => $this->movementRow($movement))
                    ->all(),
                'meta' => [
                    'total' => $page->total(),
                    'per_page' => $page->perPage(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('InventoryController@movements: '.$e->getMessage());

            return response()->json(['message' => 'Error al listar los movimientos de stock'], 500);
        }
    }

    /**
     * GET /api/inventory/alerts.
     */
    public function alerts(): JsonResponse
    {
        try {
            $lowStock = Product::query()
                ->with('category:id,name')
                ->lowStock()
                ->orderBy('stock_quantity')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => $this->productRow($product))
                ->all();

            $outOfStock = Product::query()
                ->with('category:id,name')
                ->where('stock_quantity', '<=', 0)
                ->orderBy('stock_quantity')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => $this->productRow($product))
                ->all();

            $expiring = Product::query()
                ->with('category:id,name')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', $this->expiringLimit())
                ->orderBy('expiry_date')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => $this->productRow($product))
                ->all();

            return response()->json([
                'data' => [
                    'low_stock' => $lowStock,
                    'out_of_stock' => $outOfStock,
                    'expiring' => $expiring,
                ],
                'meta' => [
                    'counts' => [
                        'low_stock' => count($lowStock),
                        'out_of_stock' => count($outOfStock),
                        'expiring' => count($expiring),
                    ],
                    'expiring_days' => Product::EXPIRING_SOON_DAYS,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('InventoryController@alerts: '.$e->getMessage());

            return response()->json(['message' => 'Error al obtener las alertas de inventario'], 500);
        }
    }

    /**
     * POST /api/inventory/receipts — la compra entra al almacén.
     */
    public function storeReceipt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'unit_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'batch_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'expiry_date' => ['sometimes', 'nullable', 'date', 'after:today'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:50'],
            'movement_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            $user = $request->user();

            $result = DB::transaction(fn () => $this->inventoryService->receive(
                $data,
                $user->id,
                BranchAccess::effectiveFilter($user, null),
            ));

            return $this->movementResponse(
                $result,
                'Recepción registrada.',
                (float) $result['movement']->quantity,
            );
        } catch (InventoryException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        } catch (\Throwable $e) {
            Log::error('InventoryController@storeReceipt: '.$e->getMessage());

            return response()->json(['message' => 'Error al registrar la recepción'], 500);
        }
    }

    /**
     * POST /api/inventory/adjustments — el conteo físico fija el saldo.
     */
    public function storeAdjustment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'counted_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'movement_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
        ]);

        try {
            $user = $request->user();

            $result = DB::transaction(fn () => $this->inventoryService->adjust(
                $data,
                $user->id,
                BranchAccess::effectiveFilter($user, null),
            ));

            $movement = $result['movement'];

            return $this->movementResponse(
                $result,
                'Ajuste registrado.',
                (float) $movement->new_stock - (float) $movement->previous_stock,
            );
        } catch (InventoryException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        } catch (\Throwable $e) {
            Log::error('InventoryController@storeAdjustment: '.$e->getMessage());

            return response()->json(['message' => 'Error al registrar el ajuste'], 500);
        }
    }

    /**
     * La respuesta de las dos escrituras: el producto ya con su saldo nuevo y
     * el movimiento, con el delta que lo explica.
     *
     * @param array{product: Product, movement: StockMovement} $result
     */
    private function movementResponse(array $result, string $message, float $delta): JsonResponse
    {
        $product = $result['product']->load('category:id,name');
        $movement = $result['movement'];

        $movement->setRelation('product', $product);
        $movement->load('createdBy:id,name');

        return response()->json([
            'data' => [
                'product' => $this->productRow($product),
                'movement' => $this->movementRow($movement),
            ],
            'meta' => [
                'message' => $message,
                'stock_quantity' => (float) $movement->new_stock,
                'delta' => round($delta, 3),
            ],
        ], 201);
    }

    private function applyProductFilters(Builder $query, Request $request): void
    {
        $term = trim((string) $request->get('q', ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

            $query->where(function (Builder $inner) use ($like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('product_category_id', $request->integer('category_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $stockStatus = $request->get('stock_status');

        if ($stockStatus === 'low') {
            $query->lowStock();
        } elseif ($stockStatus === 'out') {
            $query->where('stock_quantity', '<=', 0);
        } elseif ($stockStatus === 'expiring') {
            $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', $this->expiringLimit());
        }
    }

    /**
     * Último día que cuenta como «próximo a vencer», con el mismo número que
     * usa `Product::isExpiringSoon()`.
     */
    private function expiringLimit(): string
    {
        return now()->addDays(Product::EXPIRING_SOON_DAYS)->toDateString();
    }

    /**
     * @return array<string, int>
     */
    private function kpis(): array
    {
        return [
            'products' => Product::query()->count(),
            'low_stock' => Product::query()->lowStock()->count(),
            'out_of_stock' => Product::query()->where('stock_quantity', '<=', 0)->count(),
            'expiring_soon' => Product::query()
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', $this->expiringLimit())
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'barcode' => $product->barcode,
            'unit' => $product->unit,
            'category' => $product->category === null ? null : [
                'id' => (int) $product->category->id,
                'name' => $product->category->name,
            ],
            'cost_price' => (float) $product->cost_price,
            'sale_price' => (float) $product->sale_price,
            'stock_quantity' => (float) $product->stock_quantity,
            'minimum_stock' => (float) $product->minimum_stock,
            'maximum_stock' => $product->maximum_stock === null ? null : (float) $product->maximum_stock,
            'expiry_date' => $product->expiry_date?->toDateString(),
            'is_active' => (bool) $product->is_active,
            'low_stock' => $product->isLowStock(),
            'out_of_stock' => $product->isOutOfStock(),
            'expiring_soon' => $product->isExpiringSoon(),
        ];
    }

    /**
     * Fila del libro con su producto y su sede: el listado de movimientos.
     *
     * @return array<string, mixed>
     */
    private function movementRow(StockMovement $movement): array
    {
        return array_merge([
            'id' => (int) $movement->id,
            'product' => $movement->product === null ? null : [
                'id' => (int) $movement->product->id,
                'name' => $movement->product->name,
                'code' => $movement->product->code,
                'unit' => $movement->product->unit,
            ],
            'branch_id' => $movement->branch_id === null ? null : (int) $movement->branch_id,
        ], $this->movementDetail($movement), [
            'created_at' => $movement->created_at?->toIso8601String(),
        ]);
    }

    /**
     * El mismo detalle dentro del producto, donde el producto y la sede ya
     * están dichos por la fila que lo contiene.
     *
     * @return array<string, mixed>
     */
    private function movementTraceRow(StockMovement $movement): array
    {
        return array_merge(['id' => (int) $movement->id], $this->movementDetail($movement));
    }

    /**
     * Los hechos del movimiento, comunes al listado y al detalle del producto.
     *
     * @return array<string, mixed>
     */
    private function movementDetail(StockMovement $movement): array
    {
        return [
            'type' => $movement->type,
            'quantity' => (float) $movement->quantity,
            'previous_stock' => (float) $movement->previous_stock,
            'new_stock' => (float) $movement->new_stock,
            'unit_cost' => $movement->unit_cost === null ? null : (float) $movement->unit_cost,
            'total_cost' => $movement->total_cost === null ? null : (float) $movement->total_cost,
            'batch_number' => $movement->batch_number,
            'expiry_date' => $movement->expiry_date?->toDateString(),
            'reference' => $movement->reference,
            'reason' => $movement->reason,
            'movement_date' => $movement->movement_date?->toDateString(),
            'clinical_encounter_id' => $movement->clinical_encounter_id === null ? null : (int) $movement->clinical_encounter_id,
            'created_by' => $movement->createdBy === null ? null : [
                'id' => (int) $movement->createdBy->id,
                'name' => $movement->createdBy->name,
            ],
        ];
    }
}
