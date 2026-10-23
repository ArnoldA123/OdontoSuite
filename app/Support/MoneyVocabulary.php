<?php

namespace App\Support;

/**
 * #287, D4/D8 — the single source of the money vocabulary.
 *
 * The cash drawer and the patient ledger are two different books that used to
 * share one word. A payment and its refund move cash; a write-off (condonación)
 * and an adjustment are ledger entries that never touch the drawer; a discount
 * is a field of a charge, not a transaction. Every consumer — the service, the
 * request, the statement and the reports — reads the rules from here so the
 * four books cannot drift apart.
 */
final class MoneyVocabulary
{
    public const PAYMENT = 'payment';

    public const REFUND = 'refund';

    public const WRITE_OFF = 'write_off';

    public const ADJUSTMENT = 'adjustment';

    /**
     * Not a transaction type anymore: the discount lives in the charge
     * (`discount_amount`/`discount_type`) and only reduces what the patient
     * owes. It stays in the vocabulary because the statement and the balance
     * still need its effect.
     */
    public const DISCOUNT = 'discount';

    /** Types the API accepts when writing a transaction. */
    public const CREATABLE = [self::PAYMENT, self::REFUND, self::WRITE_OFF, self::ADJUSTMENT];

    /** Types whose money enters the drawer. */
    public const CASH_IN = [self::PAYMENT];

    /** Types whose money leaves the drawer. */
    public const CASH_OUT = [self::REFUND];

    /** Ledger entries: no drawer, no method, no cash movement. */
    public const NON_CASH = [self::WRITE_OFF, self::ADJUSTMENT];

    /** Types a register cannot sign on its own: an administrator must authorize them. */
    public const REQUIRES_AUTHORIZATION = [self::REFUND, self::WRITE_OFF, self::ADJUSTMENT];

    public static function isCreatable(string $type): bool
    {
        return in_array($type, self::CREATABLE, true);
    }

    /**
     * Whether the type moves physical cash, and therefore requires an open
     * drawer and a payment method.
     */
    public static function movesCash(string $type): bool
    {
        return in_array($type, self::CASH_IN, true) || in_array($type, self::CASH_OUT, true);
    }

    public static function requiresAuthorization(string $type): bool
    {
        return in_array($type, self::REQUIRES_AUTHORIZATION, true);
    }

    /**
     * Cash movement a type derives, or null when it is a ledger entry only.
     */
    public static function cashMovementType(string $type): ?string
    {
        return match ($type) {
            self::PAYMENT => 'income',
            self::REFUND => 'expense',
            default => null,
        };
    }

    /**
     * Signed effect of the row on the patient balance, where a charge is
     * positive debt: a payment lowers it, a refund raises it, a write-off
     * lowers it and an adjustment moves it in its own direction (a negative
     * adjustment is money owed by the clinic). A type outside the vocabulary
     * carries no balance meaning.
     */
    public static function balanceEffect(string $type, int|float|string $amount): float
    {
        $value = (float) $amount;

        return match ($type) {
            self::PAYMENT, self::WRITE_OFF, self::DISCOUNT => -$value,
            self::REFUND, self::ADJUSTMENT => $value,
            default => 0.0,
        };
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::PAYMENT => 'Pago',
            self::REFUND => 'Devolución',
            self::WRITE_OFF => 'Condonación',
            self::ADJUSTMENT => 'Ajuste',
            self::DISCOUNT => 'Descuento',
            default => $type,
        };
    }
}
