<?php

namespace App\Exceptions\Clinical;

use RuntimeException;

/**
 * F3 (#292) — the clinical history is neither erased nor amended in place.
 *
 * A clinical row that a professional signed is evidence: it keeps every field
 * it had when the act was documented, and a correction is a new addendum. The
 * models refuse the two verbs that would break that rule — rewriting an
 * addendum, and deleting an addendum, an evolution or a history — so a future
 * caller cannot reach the damage through Eloquent even when no route exposes
 * it.
 */
class ClinicalRecordImmutableException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function addendumNotEditable(): self
    {
        return new self(
            'Un addendum clínico no se edita: la corrección se agrega, no se reescribe.',
            'clinical_addendum_not_editable',
        );
    }

    public static function addendumNotDeletable(): self
    {
        return new self(
            'Un addendum clínico no se elimina: es parte del rastro auditable de la historia.',
            'clinical_addendum_not_deletable',
        );
    }

    public static function medicalRecordNotDeletable(): self
    {
        return new self(
            'Una historia clínica no se elimina: se corrige con un addendum.',
            'medical_record_not_deletable',
        );
    }

    public static function clinicalEvolutionNotDeletable(): self
    {
        return new self(
            'Una evolución clínica no se elimina: se corrige con un addendum.',
            'clinical_evolution_not_deletable',
        );
    }
}
