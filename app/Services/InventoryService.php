<?php

namespace App\Services;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Inventory\NoAdjustmentException;
use App\Exceptions\Inventory\UnknownProductException;
use App\Models\ClinicalEncounter;
use App\Models\ProcedureMaterial;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TreatmentPlanItem;
use Illuminate\Support\Collection;

/**
 * #295 (S1, S3, S5, S6) — el libro de almacén y sus tres formas de mover el
 * saldo: el consumo de un acto, la recepción por compra y el ajuste por conteo.
 *
 * Consumir un material son dos hechos escritos juntos: el material del acto
 * (`procedure_materials`, con el costo unitario congelado en ese momento) y su
 * salida del almacén (`stock_movements`, un movimiento por línea, con su lote).
 * `products.stock_quantity` sigue siendo la única verdad del saldo actual; el
 * libro es la historia.
 *
 * La guarda se evalúa sobre la demanda agregada por producto —dos líneas del
 * mismo insumo son dos lotes, no dos comprobaciones independientes— y los
 * productos se bloquean en orden de `id` para que dos cierres simultáneos hagan
 * cola en vez de interbloquearse.
 *
 * El servicio no abre transacción: quien lo llama (el cierre de la atención, la
 * recepción por compra, el ajuste por conteo) es dueño de la suya, y el bloqueo
 * de fila solo tiene sentido dentro de ella.
 */
class InventoryService
{
    /**
     * Recepción por compra (S6): el lote entra al almacén.
     *
     * Suma lo recibido al saldo y lo deja escrito como movimiento `in`. El
     * costo del lote es el que declara la recepción o, si no lo declara, el del
     * catálogo; en ningún caso la recepción reprecifica el catálogo, porque el
     * costo de compra de un lote no es el costo vigente del insumo.
     *
     * @param  array<string, mixed>                             $data quantity, unit_cost, batch_number, expiry_date, reference, movement_date, notes
     * @return array{product: Product, movement: StockMovement}
     */
    public function receive(array $data, ?int $userId, ?int $branchId): array
    {
        $product = $this->lockProduct((int) $data['product_id']);

        $quantity = round((float) $data['quantity'], 3);
        $previous = (float) $product->stock_quantity;
        $new = round($previous + $quantity, 3);
        $unitCost = isset($data['unit_cost'])
            ? (float) $data['unit_cost']
            : (float) $product->cost_price;

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'branch_id' => $branchId,
            'created_by' => $userId,
            'type' => 'in',
            'quantity' => $quantity,
            'previous_stock' => $previous,
            'new_stock' => $new,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
            'reference' => $data['reference'] ?? null,
            'reason' => 'Recepción por compra',
            'notes' => $data['notes'] ?? null,
            'movement_date' => $data['movement_date'] ?? now()->toDateString(),
            'expiry_date' => $data['expiry_date'] ?? null,
            'batch_number' => $data['batch_number'] ?? null,
        ]);

        $product->forceFill(['stock_quantity' => $new])->save();

        return ['product' => $product, 'movement' => $movement];
    }

    /**
     * Ajuste por conteo (S6): el saldo queda fijado en lo contado.
     *
     * La diferencia se escribe en magnitud positiva aunque el conteo baje el
     * saldo: la dirección del movimiento se lee de `previous_stock` y
     * `new_stock`, no del signo de la cantidad, que es lo que el libro ya hace
     * en el consumo. Un conteo que coincide no es un ajuste cero: es un rechazo
     * y no se escribe nada.
     *
     * @param  array<string, mixed>                             $data counted_quantity, reason, notes, movement_date
     * @return array{product: Product, movement: StockMovement}
     *
     * @throws NoAdjustmentException cuando no hay diferencia que registrar
     */
    public function adjust(array $data, ?int $userId, ?int $branchId): array
    {
        $product = $this->lockProduct((int) $data['product_id']);

        $previous = (float) $product->stock_quantity;
        $counted = round((float) $data['counted_quantity'], 3);
        $delta = round($counted - $previous, 3);

        if ($delta === 0.0) {
            throw NoAdjustmentException::forCount($counted);
        }

        $unitCost = (float) $product->cost_price;

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'branch_id' => $branchId,
            'created_by' => $userId,
            'type' => 'adjustment',
            'quantity' => abs($delta),
            'previous_stock' => $previous,
            'new_stock' => $counted,
            'unit_cost' => $unitCost,
            'total_cost' => round(abs($delta) * $unitCost, 2),
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'movement_date' => $data['movement_date'] ?? now()->toDateString(),
        ]);

        $product->forceFill(['stock_quantity' => $counted])->save();

        return ['product' => $product, 'movement' => $movement];
    }

    /**
     * Consume los materiales de un acto.
     *
     * @param  array<int, array<string, mixed>>                                                      $lines una línea por lote consumido
     * @return array{materials: array<int, ProcedureMaterial>, movements: array<int, StockMovement>}
     */
    public function consumeForEncounter(
        ClinicalEncounter $encounter,
        array $lines,
        ?int $userId,
        ?int $branchId,
    ): array {
        if ($lines === []) {
            return ['materials' => [], 'movements' => []];
        }

        $products = $this->lockProducts($lines);
        $this->guardStock($lines, $products);

        $branchId = $encounter->appointment?->branch_id ?? $branchId;
        $planItemIds = $this->planItemIdsFor($encounter);
        $balance = [];
        $materials = [];
        $movements = [];

        foreach ($lines as $line) {
            $product = $products->get((int) $line['product_id']);
            $quantity = round((float) ($line['quantity_used'] ?? 1), 3);
            $unitCost = isset($line['unit_cost'])
                ? (float) $line['unit_cost']
                : (float) $product->cost_price;
            $totalCost = round($quantity * $unitCost, 2);

            $previous = $balance[$product->id] ?? (float) $product->stock_quantity;
            $balance[$product->id] = round($previous - $quantity, 3);

            $materials[] = ProcedureMaterial::create([
                'appointment_id' => $encounter->appointment_id,
                'clinical_encounter_id' => $encounter->id,
                'treatment_plan_item_id' => $this->planItemId($line, $planItemIds),
                'product_id' => $product->id,
                'created_by' => $userId,
                'quantity_used' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'batch_number' => $line['batch_number'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'notes' => $line['notes'] ?? null,
            ]);

            $movements[] = StockMovement::create([
                'product_id' => $product->id,
                'clinical_encounter_id' => $encounter->id,
                'branch_id' => $branchId,
                'created_by' => $userId,
                'type' => 'out',
                'quantity' => $quantity,
                'previous_stock' => $previous,
                'new_stock' => $balance[$product->id],
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference' => 'ENC-'.$encounter->id,
                'reason' => 'Consumo en atención',
                'movement_date' => now()->toDateString(),
                'batch_number' => $line['batch_number'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
            ]);
        }

        // Una sola escritura por producto: el saldo es el de la suma consumida.
        foreach ($products as $product) {
            $product->forceFill(['stock_quantity' => $balance[$product->id]])->save();
        }

        return ['materials' => $materials, 'movements' => $movements];
    }

    /**
     * Un solo producto bloqueado para una escritura de almacén.
     *
     * @throws UnknownProductException
     */
    private function lockProduct(int $productId): Product
    {
        $product = Product::query()
            ->whereKey($productId)
            ->lockForUpdate()
            ->first();

        if ($product === null) {
            throw UnknownProductException::forProduct($productId);
        }

        return $product;
    }

    /**
     * @param  array<int, array<string, mixed>> $lines
     * @return Collection<int, Product>
     */
    private function lockProducts(array $lines): Collection
    {
        $ids = [];
        foreach ($lines as $line) {
            $ids[(int) ($line['product_id'] ?? 0)] = true;
        }

        $ids = array_keys($ids);
        sort($ids);

        return Product::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * S2: el acto se rechaza cuando el almacén no puede responder por la
     * demanda, y el rechazo nombra el insumo, lo disponible y lo solicitado.
     *
     * @param array<int, array<string, mixed>> $lines
     * @param Collection<int, Product>         $products
     */
    private function guardStock(array $lines, Collection $products): void
    {
        $demand = [];
        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $demand[$productId] = round(($demand[$productId] ?? 0.0) + (float) ($line['quantity_used'] ?? 1), 3);
        }
        ksort($demand);

        foreach ($demand as $productId => $quantity) {
            $product = $products->get($productId);
            if ($product === null) {
                throw UnknownProductException::forProduct($productId);
            }

            $available = (float) $product->stock_quantity;
            if ($available < $quantity) {
                throw InsufficientStockException::forProduct($product, $available, $quantity);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function planItemIdsFor(ClinicalEncounter $encounter): array
    {
        if (! $encounter->treatment_plan_id) {
            return [];
        }

        return TreatmentPlanItem::where('treatment_plan_id', $encounter->treatment_plan_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * La línea solo puede apuntar a un ítem del plan que el acto documenta.
     *
     * @param array<string, mixed> $line
     * @param array<int, int>      $planItemIds
     */
    private function planItemId(array $line, array $planItemIds): ?int
    {
        if ($planItemIds === [] || empty($line['treatment_plan_item_id'])) {
            return null;
        }

        $candidate = (int) $line['treatment_plan_item_id'];

        return in_array($candidate, $planItemIds, true) ? $candidate : null;
    }
}
