<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Slice 03 (T-03.8): singleton tracking row for ReminderProvider.
 * Holds the last_run_at + runs_count used for 60s idempotency.
 */
class ReminderProviderRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_run_at',
        'runs_count',
        'last_processed',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'runs_count' => 'integer',
        'last_processed' => 'integer',
    ];
}
