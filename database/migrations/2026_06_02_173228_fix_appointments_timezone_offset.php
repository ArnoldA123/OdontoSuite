<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        // bugfix-2026-08 slice 05: driver-conditional guard so SQLite local
        // tests do not crash on MySQL-only DATE_SUB syntax. The historical
        // MySQL behaviour (subtract 5h from scheduled_at/ends_at) is
        // preserved exactly when running on MySQL.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointments', function ($table) {
            $table->dropUnique('unique_user_time_slot');
            $table->dropUnique('unique_chair_time_slot');
        });

        $affected = DB::table('appointments')
            ->whereNotNull('scheduled_at')
            ->update([
                'scheduled_at' => DB::raw('DATE_SUB(scheduled_at, INTERVAL 5 HOUR)'),
                'ends_at' => DB::raw('DATE_SUB(ends_at, INTERVAL 5 HOUR)'),
            ]);

        Schema::table('appointments', function ($table) {
            $table->unique(['user_id', 'scheduled_at', 'ends_at'], 'unique_user_time_slot');
            $table->unique(['dental_chair_id', 'scheduled_at', 'ends_at'], 'unique_chair_time_slot');
        });

        Log::info('Timezone fix migration: adjusted ' . $affected . ' appointments by -5 hours');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointments', function ($table) {
            $table->dropUnique('unique_user_time_slot');
            $table->dropUnique('unique_chair_time_slot');
        });

        DB::table('appointments')
            ->whereNotNull('scheduled_at')
            ->update([
                'scheduled_at' => DB::raw('DATE_ADD(scheduled_at, INTERVAL 5 HOUR)'),
                'ends_at' => DB::raw('DATE_ADD(ends_at, INTERVAL 5 HOUR)'),
            ]);

        Schema::table('appointments', function ($table) {
            $table->unique(['user_id', 'scheduled_at', 'ends_at'], 'unique_user_time_slot');
            $table->unique(['dental_chair_id', 'scheduled_at', 'ends_at'], 'unique_chair_time_slot');
        });
    }
};
