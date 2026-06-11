<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'origin_appointment_id',
        'created_by',
        'plan_number',
        'title',
        'description',
        'status',
        'total_cost',
        'discount_amount',
        'final_cost',
        'estimated_duration_weeks',
        'start_date',
        'end_date',
        'last_activity_at',
        'notes',
        'patient_notes',
        'phases',
        'requires_anesthesia',
        'is_urgent'
    ];

    protected $casts = [
        'phases' => 'array',
        'requires_anesthesia' => 'boolean',
        'is_urgent' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_activity_at' => 'datetime',
    ];

    // Relaciones
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function paymentPlans(): HasMany
    {
        return $this->hasMany(PaymentPlan::class);
    }

    public function originAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'origin_appointment_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Métricas de progreso del plan (derivadas, no se persisten).
     *
     * @return array<string, mixed>
     */
    public function progressMetrics(): array
    {
        $items = $this->items;

        $total = $items->count();
        $completed = $items->where('status', 'completed')->count();
        $inProgress = $items->where('status', 'in_progress')->count();
        $pending = $items->whereIn('status', ['pending', 'proposed'])->count();
        $cancelled = $items->where('status', 'cancelled')->count();

        $progressPct = $total > 0
            ? (int) round((($completed + ($inProgress * 0.5)) / $total) * 100)
            : 0;

        $completedCost = $items->where('status', 'completed')->sum(fn ($i) => (float) $i->total_cost);
        $pendingCost = $items->whereIn('status', ['pending', 'in_progress'])->sum(fn ($i) => (float) $i->total_cost);

        return [
            'total_items' => $total,
            'completed_items' => $completed,
            'in_progress_items' => $inProgress,
            'pending_items' => $pending,
            'cancelled_items' => $cancelled,
            'progress_percentage' => $progressPct,
            'completed_cost' => round($completedCost, 2),
            'remaining_cost' => round($pendingCost, 2),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
        ];
    }

    public function isOverdue(): bool
    {
        if (! $this->end_date) {
            return false;
        }

        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        return $this->end_date->lt(now()->startOfDay());
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'proposed', 'approved', 'in_progress']);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }
}
