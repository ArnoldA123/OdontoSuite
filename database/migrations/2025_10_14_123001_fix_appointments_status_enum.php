<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // AGENTS.md §6: pre-existing SQLite MODIFY COLUMN tech debt.
        // Skip the raw ALTER on SQLite (test env) and rely on Doctrine/Schema
        // for local migration. MySQL still gets the canonical ENUM widening.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('scheduled', 'confirmed', 'in_consultation', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled') DEFAULT 'scheduled'");

        DB::table('appointments')
            ->where('status', 'in_progress')
            ->update(['status' => 'in_consultation']);
    }

    public function down(): void
    {
        DB::table('appointments')
            ->where('status', 'in_consultation')
            ->update(['status' => 'in_progress']);
        
        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled') DEFAULT 'scheduled'");
    }
};
