<?php

namespace App\Exceptions\Encounter;

/**
 * F6 (#286) — the close is signed by the professional of the act.
 *
 * The signature is the simple electronic signature the record can prove: the
 * professional re-authenticates with their own password and the system freezes
 * a SHA-256 of the clinical content. The three refusals are distinct because the
 * SPA shows a different field for each: missing signature, wrong password and a
 * signer who is not the professional who held the act.
 */
class ClinicalSignatureException extends EncounterException
{
    public static function missing(): self
    {
        return new self(
            'La atención se firma al cerrarse: falta la firma del profesional.',
            'clinical_signature_required',
            422,
        );
    }

    public static function invalidCredentials(): self
    {
        return new self(
            'La contraseña no confirma la firma del profesional.',
            'clinical_signature_invalid_credentials',
            422,
        );
    }

    public static function notTheProfessional(int $userId, ?int $signerId): self
    {
        return new self(
            "Solo el profesional de la atención puede firmarla (atención del usuario {$userId}).",
            'clinical_signature_not_professional',
            403,
            ['professional_id' => $userId, 'signer_id' => $signerId],
        );
    }
}
