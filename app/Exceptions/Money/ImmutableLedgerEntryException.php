<?php

namespace App\Exceptions\Money;

use RuntimeException;

/**
 * #287 (T2) — the patient charge ledger is evidence of a debt.
 *
 * A charge is what the clinic billed: the closure of an act writes it and the
 * counter records the ones no act produced. Rewriting or deleting the row
 * would make the statement disagree with the history it documents, so the
 * correction is an adjustment (#287, D1) and the model refuses the two verbs
 * that would break that rule.
 */
class ImmutableLedgerEntryException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function chargeNotEditable(): self
    {
        return new self(
            'Un cargo del paciente no se edita: la corrección se registra como un ajuste.',
            'patient_charge_not_editable',
        );
    }

    public static function chargeNotDeletable(): self
    {
        return new self(
            'Un cargo del paciente no se elimina: la corrección se registra como un ajuste.',
            'patient_charge_not_deletable',
        );
    }
}
