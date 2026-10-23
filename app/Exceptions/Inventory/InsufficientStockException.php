<?php

namespace App\Exceptions\Inventory;

use App\Models\Product;

/**
 * #295 (S2) — el acto pide más de lo que el almacén tiene.
 *
 * La cantidad comparada es la demanda agregada del producto, no la de una
 * línea: dos lotes del mismo insumo se suman antes de mirar el saldo.
 */
class InsufficientStockException extends InventoryException
{
    public static function forProduct(Product $product, float $available, float $requested): self
    {
        return new self(
            sprintf(
                'Stock insuficiente para «%s»: disponible %s, solicitado %s.',
                $product->name,
                self::quantity($available),
                self::quantity($requested),
            ),
            'insufficient_stock',
            422,
            [
                'product_id' => $product->id,
                'product' => $product->name,
                'available' => $available,
                'requested' => $requested,
            ],
        );
    }
}
