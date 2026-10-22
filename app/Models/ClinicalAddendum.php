<?php

namespace App\Models;

use App\Exceptions\Clinical\ClinicalRecordImmutableException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F3 (#292) — the correction note.
 *
 * A history and its evolutions are never amended in place: when a professional
 * needs to correct what was documented, they add a note that carries its own
 * author (`user_id`), date (`created_at`) and reason (`reason`). The original
 * row keeps every field it had.
 *
 * The addendum is append-only. The model rejects `updating` and `deleting`, so
 * no caller — route, command or test helper — can rewrite the trail after the
 * fact.
 */
class ClinicalAddendum extends Model
{
    use HasFactory;

    /**
     * Append-only rows have no update timestamp; the column does not exist.
     */
    public const UPDATED_AT = null;

    /**
     * The inflector pluralizes "addendum" as "addendums"; the table follows
     * the Latin plural the clinical vocabulary uses.
     */
    protected $table = 'clinical_addenda';

    protected $fillable = [
        'medical_record_id',
        'clinical_evolution_id',
        'user_id',
        'reason',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw ClinicalRecordImmutableException::addendumNotEditable());
        static::deleting(fn () => throw ClinicalRecordImmutableException::addendumNotDeletable());
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /**
     * Null when the correction belongs to the history as a whole instead of one
     * visit.
     */
    public function clinicalEvolution(): BelongsTo
    {
        return $this->belongsTo(ClinicalEvolution::class);
    }

    /**
     * The professional who signed the correction.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
