<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ProcedureBranchPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Branch tariff administration (#290 S8).
 *
 * The list price of the catalog is the group agreement; a row here is what one
 * sede charges, optionally inside a validity window. A sede administrator only
 * reaches its own branch: `EnforceBranchScope` rejects a foreign `{branch}`
 * with 403 before this controller runs.
 */
class ProcedureBranchPriceController extends Controller
{
    public function index(Request $request, Branch $branch): JsonResponse
    {
        try {
            $validated = $request->validate([
                'on' => 'nullable|date',
                'q' => 'nullable|string|max:100',
                'price_id' => 'nullable|integer',
            ]);

            $date = isset($validated['on']) ? Carbon::parse($validated['on'])->toDateString() : null;

            $query = ProcedureBranchPrice::query()
                ->with('procedure:id,code,name,default_cost,specialty_id,is_active')
                ->forBranch($branch->id)
                ->when($date, fn ($q) => $q->inForceOn($date))
                ->when($validated['q'] ?? null, function ($q, $term) {
                    $q->whereHas('procedure', fn ($p) => $p
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%"));
                })
                ->orderByDesc('valid_from')
                ->orderByDesc('id');

            return response()->json([
                'data' => $query->get()->map(fn (ProcedureBranchPrice $price) => [
                    'id' => $price->id,
                    'procedure_id' => $price->procedure_catalog_id,
                    'procedure_code' => $price->procedure?->code,
                    'procedure_name' => $price->procedure?->name,
                    'list_price' => $price->procedure ? round((float) $price->procedure->default_cost, 2) : null,
                    'price' => round((float) $price->price, 2),
                    'valid_from' => $price->valid_from?->toDateString(),
                    'valid_to' => $price->valid_to?->toDateString(),
                    'is_active' => (bool) $price->is_active,
                ])->all(),
                'meta' => [
                    'branch_id' => $branch->id,
                    'on' => $date,
                    'total' => $query->count(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('No se pudo listar el tarifario de la sede', ['exception' => $e]);

            return response()->json(['message' => 'Error al listar el tarifario de la sede'], 500);
        }
    }

    /**
     * Bulk upsert keyed by (procedure, branch, valid_from).
     */
    public function update(Request $request, Branch $branch): JsonResponse
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.procedure_catalog_id' => ['required', 'integer', Rule::exists('procedure_catalog', 'id')],
                'items.*.price' => 'required|numeric|min:0|max:999999.99',
                'items.*.valid_from' => 'nullable|date',
                'items.*.valid_to' => 'nullable|date',
                'items.*.is_active' => 'sometimes|boolean',
            ]);

            $saved = [];

            foreach ($validated['items'] as $item) {
                $validFrom = isset($item['valid_from'])
                    ? Carbon::parse($item['valid_from'])->toDateString()
                    : null;
                $validTo = isset($item['valid_to'])
                    ? Carbon::parse($item['valid_to'])->toDateString()
                    : null;

                if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => ['La vigencia final no puede ser anterior al inicio.'],
                    ]);
                }

                $saved[] = ProcedureBranchPrice::query()->updateOrCreate(
                    [
                        'procedure_catalog_id' => $item['procedure_catalog_id'],
                        'branch_id' => $branch->id,
                        'valid_from' => $validFrom,
                    ],
                    [
                        'price' => $item['price'],
                        'valid_to' => $validTo,
                        'is_active' => $item['is_active'] ?? true,
                    ],
                );
            }

            return response()->json([
                'data' => collect($saved)->map(fn (ProcedureBranchPrice $price) => [
                    'id' => $price->id,
                    'procedure_id' => $price->procedure_catalog_id,
                    'price' => round((float) $price->price, 2),
                    'valid_from' => $price->valid_from?->toDateString(),
                    'valid_to' => $price->valid_to?->toDateString(),
                    'is_active' => (bool) $price->is_active,
                ])->all(),
                'meta' => ['message' => 'Tarifario de la sede actualizado'],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('No se pudo actualizar el tarifario de la sede', ['exception' => $e]);

            return response()->json(['message' => 'Error al actualizar el tarifario de la sede'], 500);
        }
    }

    public function destroy(Branch $branch, int $priceId): JsonResponse
    {
        $price = ProcedureBranchPrice::query()
            ->forBranch($branch->id)
            ->findOrFail($priceId);

        $price->delete();

        return response()->json([
            'data' => null,
            'meta' => ['message' => 'Precio de sede eliminado'],
        ]);
    }
}
