<?php

namespace App\Models;

use App\Models\Concerns\ScopesToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F5 (#285) — a case the clinic sends to the prosthetic laboratory.
 *
 * The case is the laboratory's work order: patient, piece, job, material, the
 * date it was sent and the date it was promised, plus the assigned technician.
 * It deliberately carries no clinical content, so the laboratory desk can do
 * its job without reading the clinical record (S3, S5).
 *
 * `status` is the five-phase machine of S2 and only moves forward. `delivered`
 * is the phase that means "ready": the work came back from the workshop and can
 * be placed, and that is when the professional and reception are notified (S4).
 */
class LabCase extends Model
{
    use HasFactory, ScopesToBranch;

    public const PHASE_IN_PREPARATION = 'in_preparation';

    public const PHASE_IN_LABORATORY = 'in_laboratory';

    public const PHASE_TRY_IN = 'try_in';

    public const PHASE_DELIVERED = 'delivered';

    public const PHASE_PLACED = 'placed';

    /**
     * The phases in the order the work happens. The index of a phase in this
     * list is its position in the machine: a later index may be reached, the
     * same or an earlier one never.
     *
     * @var list<string>
     */
    public const PHASES = [
        self::PHASE_IN_PREPARATION,
        self::PHASE_IN_LABORATORY,
        self::PHASE_TRY_IN,
        self::PHASE_DELIVERED,
        self::PHASE_PLACED,
    ];

    protected $fillable = [
        'patient_id',
        'branch_id',
        'professional_id',
        'technician_id',
        'dental_piece_id',
        'work',
        'material',
        'sent_at',
        'promised_at',
        'status',
        'notes',
        'delivered_at',
        'placed_at',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::PHASE_IN_PREPARATION,
    ];

    protected $casts = [
        'sent_at' => 'date',
        'promised_at' => 'date',
        'delivered_at' => 'datetime',
        'placed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function dentalPiece(): BelongsTo
    {
        return $this->belongsTo(DentalPiece::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeInPhase(Builder $query, string $phase): Builder
    {
        return $query->where('status', $phase);
    }

    /**
     * Cases waiting to be placed: they came back from the workshop.
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::PHASE_DELIVERED);
    }

    /**
     * Open cases whose promised date already passed.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [self::PHASE_DELIVERED, self::PHASE_PLACED])
            ->whereDate('promised_at', '<', now()->toDateString());
    }

    /**
     * Position of a phase in the machine, or null for an unknown value.
     */
    public static function phaseIndex(?string $phase): ?int
    {
        $index = array_search($phase, self::PHASES, true);

        return $index === false ? null : $index;
    }
}
