<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F6 (#286) — one coded diagnosis of one clinical act.
 *
 * The row keeps a copy of `cie_code` and `name` because the record is signed
 * over its content: editing or retiring a catalogue entry must not change what
 * a professional signed on a past attention.
 */
class EncounterDiagnosis extends Model
{
    use HasFactory;

    public const TYPE_PRESUMPTIVE = 'presumptive';

    public const TYPE_DEFINITIVE = 'definitive';

    public const TYPE_RULE_OUT = 'rule_out';

    public const TYPES = [
        self::TYPE_PRESUMPTIVE,
        self::TYPE_DEFINITIVE,
        self::TYPE_RULE_OUT,
    ];

    protected $fillable = [
        'clinical_encounter_id',
        'diagnosis_catalog_id',
        'cie_code',
        'name',
        'type',
        'notes',
    ];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(DiagnosisCatalog::class, 'diagnosis_catalog_id');
    }
}
