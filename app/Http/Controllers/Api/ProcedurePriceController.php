<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payer;
use App\Models\ProcedurePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tariff administration (#290 S8, #293 S3).
 *
 * One table, two doors. The sede door (`branches/{branch}/procedure-prices`)
 * writes rows without a convenio and keeps the behaviour of #290: a sede
 * administrator only reaches its own branch, because `EnforceBranchScope`
 * rejects a foreign `{branch}` with 403 before this controller runs. The
 * convenio door (`payers/{payer}/procedure-prices`) writes rows agreed with the
 * convenio, optionally for one sede, and requires group scope: a price agreed
 * for the whole group is not a sede decision.
 */
class ProcedurePriceController extends Controller
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

            $query = ProcedurePrice::query()
                ->with('procedure:id,code,name,default_cost,specialty_id,is_active')
                ->forBranch($branch->id)
                ->whereNull('payer_id')
                ->when($date, fn ($q) => $q->inForceOn($date))
                ->when($validated['q'] ?? null, function ($q, $term) {
                    $q->whereHas('procedure', fn ($p) => $p
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%"));
                })
                ->orderByDesc('valid_from')
                ->orderByDesc('id');

            return response()->json([
                'data' => $query->get()->map(fn (ProcedurePrice $price) => [
                    'id' => $price->id,
                    'payer_id' => $price->payer_id,
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
        } catch (ValidationException $e) {
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
                    throw ValidationException::withMessages([
                        'items' => ['La vigencia final no puede ser anterior al inicio.'],
                    ]);
                }

                $saved[] = ProcedurePrice::query()->updateOrCreate(
                    [
                        'procedure_catalog_id' => $item['procedure_catalog_id'],
                        'branch_id' => $branch->id,
                        'payer_id' => null,
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
                'data' => collect($saved)->map(fn (ProcedurePrice $price) => [
                    'id' => $price->id,
                    'payer_id' => $price->payer_id,
                    'procedure_id' => $price->procedure_catalog_id,
                    'price' => round((float) $price->price, 2),
                    'valid_from' => $price->valid_from?->toDateString(),
                    'valid_to' => $price->valid_to?->toDateString(),
                    'is_active' => (bool) $price->is_active,
                ])->all(),
                'meta' => ['message' => 'Tarifario de la sede actualizado'],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('No se pudo actualizar el tarifario de la sede', ['exception' => $e]);

            return response()->json(['message' => 'Error al actualizar el tarifario de la sede'], 500);
        }
    }

    public function destroy(Branch $branch, int $priceId): JsonResponse
    {
        $price = ProcedurePrice::query()
            ->forBranch($branch->id)
            ->whereNull('payer_id')
            ->findOrFail($priceId);

        $price->delete();

        return response()->json([
            'data' => null,
            'meta' => ['message' => 'Precio de sede eliminado'],
        ]);
    }

    /**
     * Rows agreed with the convenio, in every sede.
     */
    public function payerIndex(Request $request, Payer $payer): JsonResponse
    {
        try {
            $validated = $request->validate([
                'on' => 'nullable|date',
                'q' => 'nullable|string|max:100',
            ]);

            $date = isset($validated['on']) ? Carbon::parse($validated['on'])->toDateString() : null;

            $query = ProcedurePrice::query()
                ->with([
                    'procedure:id,code,name,default_cost,specialty_id,is_active',
                    'branch:id,name',
                ])
                ->forPayer($payer->id)
                ->when($date, fn ($q) => $q->inForceOn($date))
                ->when($validated['q'] ?? null, function ($q, $term) {
                    $q->whereHas('procedure', fn ($p) => $p
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%"));
                })
                ->orderByDesc('valid_from')
                ->orderByDesc('id');

            return response()->json([
                'data' => $query->get()->map(fn (ProcedurePrice $price) => [
                    'id' => $price->id,
                    'payer_id' => $price->payer_id,
                    'branch_id' => $price->branch_id,
                    'branch_name' => $price->branch?->name,
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
                    'payer_id' => $payer->id,
                    'on' => $date,
                    'total' => $query->count(),
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('No se pudo listar el tarifario del convenio', ['exception' => $e]);

            return response()->json(['message' => 'Error al listar el tarifario del convenio'], 500);
        }
    }

    /**
     * Bulk upsert keyed by (procedure, payer, branch, valid_from). The branch is
     * optional: without it the row is the price agreed for the whole group.
     */
    public function payerUpdate(Request $request, Payer $payer): JsonResponse
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.procedure_catalog_id' => ['required', 'integer', Rule::exists('procedure_catalog', 'id')],
                'items.*.price' => 'required|numeric|min:0|max:999999.99',
                'items.*.valid_from' => 'nullable|date',
                'items.*.valid_to' => 'nullable|date',
                'items.*.is_active' => 'sometimes|boolean',
                'items.*.branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
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
                    throw ValidationException::withMessages([
                        'items' => ['La vigencia final no puede ser anterior al inicio.'],
                    ]);
                }

                $saved[] = ProcedurePrice::query()->updateOrCreate(
                    [
                        'procedure_catalog_id' => $item['procedure_catalog_id'],
                        'payer_id' => $payer->id,
                        'branch_id' => $item['branch_id'] ?? null,
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
                'data' => collect($saved)->map(fn (ProcedurePrice $price) => [
                    'id' => $price->id,
                    'payer_id' => $price->payer_id,
                    'branch_id' => $price->branch_id,
                    'procedure_id' => $price->procedure_catalog_id,
                    'price' => round((float) $price->price, 2),
                    'valid_from' => $price->valid_from?->toDateString(),
                    'valid_to' => $price->valid_to?->toDateString(),
                    'is_active' => (bool) $price->is_active,
                ])->all(),
                'meta' => ['message' => 'Tarifario del convenio actualizado'],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('No se pudo actualizar el tarifario del convenio', ['exception' => $e]);

            return response()->json(['message' => 'Error al actualizar el tarifario del convenio'], 500);
        }
    }

    public function payerDestroy(Payer $payer, int $priceId): JsonResponse
    {
        $price = ProcedurePrice::query()
            ->forPayer($payer->id)
            ->findOrFail($priceId);

        $price->delete();

        return response()->json([
            'data' => null,
            'meta' => ['message' => 'Precio de convenio eliminado'],
        ]);
    }
}
