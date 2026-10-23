<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AppointmentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'default_duration_minutes',
        'price',
        'default_procedure_catalog_id',
        'color',
        'requires_confirmation',
        'is_active',
        // #295 (S7): columna legacy. Se sigue aceptando al escribir para no
        // romper el formulario, pero la exigencia sale del recetario.
        'requires_materials',
        'is_consultation_mode',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'requires_confirmation' => 'boolean',
        'is_active' => 'boolean',
        'requires_materials' => 'boolean',
        'is_consultation_mode' => 'boolean',
    ];

    /**
     * Get the appointments for the appointment type.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * #295 (S7): procedimiento que el tipo documenta por defecto.
     */
    public function defaultProcedureCatalog(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'default_procedure_catalog_id');
    }

    /**
     * #295 (S7): un tipo exige materiales cuando su procedimiento por defecto
     * declara receta. Es el valor que publican las respuestas; la columna
     * `requires_materials` dejo de decidir.
     */
    public function requiresMaterialsFromCatalog(): bool
    {
        $procedure = $this->defaultProcedureCatalog;

        if (! $procedure instanceof ProcedureCatalog) {
            return false;
        }

        return $procedure->materialRecipe->isNotEmpty();
    }

    /**
     * #295 (S7): cuerpo publico del tipo, con `requires_materials` derivado.
     *
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        return array_merge($this->toArray(), [
            'requires_materials' => $this->requiresMaterialsFromCatalog(),
        ]);
    }

    /**
     * Get the waiting lists for the appointment type.
     */
    public function waitingLists(): HasMany
    {
        return $this->hasMany(WaitingList::class);
    }

    /**
     * Scope a query to only include active appointment types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the audit logs for the appointment type.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
