<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdontogramRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'odontogram_id',
        'dental_piece_id',
        'tooth_surface_id',
        'condition_code',
        'condition_name',
        'diagnosis',
        'treatment_notes',
        'color',
        'appointment_id',
        'created_by'
    ];

    // Relaciones
    public function odontogram(): BelongsTo
    {
        return $this->belongsTo(Odontogram::class);
    }

    public function dentalPiece(): BelongsTo
    {
        return $this->belongsTo(DentalPiece::class);
    }

    public function toothSurface(): BelongsTo
    {
        return $this->belongsTo(ToothSurface::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
