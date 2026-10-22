<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * F1 (#281) — custody of the patient between reception and the professional.
 *
 * `arrived` becomes a real appointment status: reception marking the patient
 * present is not the same act as the professional starting the consultation.
 * The custody trail keeps author, hour and door for every transfer, and
 * `checked_in_by` records who registered the arrival (the professional may do
 * it as a fallback when reception is absent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('checked_in_by')
                ->nullable()
                ->after('checked_in_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('appointment_custody_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->string('door', 16);
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['appointment_id', 'created_at']);
        });

        // Same guard as 2025_10_14_123001_fix_appointments_status_enum: SQLite
        // cannot MODIFY an ENUM column, and the DB-backed suite runs on MySQL.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('scheduled', 'confirmed', 'arrived', 'in_consultation', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled') DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::table('appointments')
                ->where('status', 'arrived')
                ->update(['status' => 'confirmed']);

            DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('scheduled', 'confirmed', 'in_consultation', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled') DEFAULT 'scheduled'");
        }

        Schema::dropIfExists('appointment_custody_logs');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_by');
        });
    }
};
