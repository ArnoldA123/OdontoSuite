<?php

use App\Providers\ReminderProvider;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Slice 03 (T-03.5): ReminderProvider dispatched hourly. Single callable
// keeps the wiring small; provider handles its own idempotency + try/catch.
Schedule::call(function () {
    app(ReminderProvider::class)->dispatch();
})
    ->hourly()
    ->name('reminders-dispatch')
    ->withoutOverlapping(5); // 5-min lock window (provider also self-gates at 60s)
