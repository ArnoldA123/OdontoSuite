<?php

namespace Tests\Support;

use App\Models\DiagnosisCatalog;

/**
 * F6 (#286) — the NTS 139 minimums every closure payload must carry.
 *
 * Sharing the three members here keeps the closure tests about what they test
 * (materials, continuity, plan sessions) instead of repeating a complaint, a
 * CIE-10 code and the professional's password in every payload.
 */
trait ClinicalCompliancePayload
{
    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function compliancePayload(array $overrides = []): array
    {
        return array_merge([
            'reason_for_consultation' => 'Dolor en la pieza 16 desde hace tres días.',
            'diagnoses' => [
                [
                    'diagnosis_catalog_id' => $this->diagnosisEntry()->id,
                    'type' => 'definitive',
                    'notes' => 'Lesión oclusal.',
                ],
            ],
            'signature' => ['password' => 'password'],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function diagnosisEntry(array $attributes = []): DiagnosisCatalog
    {
        $attributes = array_merge([
            'cie_code' => 'K02.1',
            'name' => 'Caries de la dentina',
        ], $attributes);

        return DiagnosisCatalog::query()->firstOrCreate(
            ['cie_code' => $attributes['cie_code']],
            $attributes,
        );
    }
}
