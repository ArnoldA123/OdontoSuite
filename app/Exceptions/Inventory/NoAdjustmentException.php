<?php

namespace App\Exceptions\Inventory;

/**
 * #295 (S6) — el conteo físico coincide con el saldo registrado.
 *
 * Un ajuste por conteo existe para mover el saldo hacia lo contado. Cuando los
 * dos números son el mismo no hay hecho que registrar: aceptar el ajuste
 * escribiría un movimiento `adjustment` de cantidad cero que ensucia el libro
 * sin corregir nada.
 */
class NoAdjustmentException extends InventoryException
{
    public static function forCount(float $counted): self
    {
        return new self(
            sprintf(
                'El conteo coincide con el stock registrado (%s): no hay ajuste que registrar.',
                self::quantity($counted),
            ),
            'no_adjustment',
            422,
            ['counted_quantity' => $counted],
        );
    }
}
