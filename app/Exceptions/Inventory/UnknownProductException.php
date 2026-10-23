<?php

namespace App\Exceptions\Inventory;

/**
 * #295 — la línea nombra un insumo que no está en el catálogo.
 *
 * El cierre lo valida en la puerta (`exists:products,id`); esta excepción es la
 * salvaguarda del servicio para que una llamada interna nunca omita la línea en
 * silencio dejando el acto con menos materiales de los que declara.
 */
class UnknownProductException extends InventoryException
{
    public static function forProduct(int $productId): self
    {
        return new self(
            "El insumo solicitado no existe en el catálogo: producto {$productId}.",
            'unknown_product',
            422,
            ['product_id' => $productId],
        );
    }
}
