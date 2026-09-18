<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reminder_schedules')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `reminder_schedules` MODIFY `status` ENUM('pending','queued','sent','failed','cancelled') NOT NULL DEFAULT 'pending'"
            );

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('reminder_schedules', function ($table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('reminder_schedules')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `reminder_schedules` MODIFY `status` ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
