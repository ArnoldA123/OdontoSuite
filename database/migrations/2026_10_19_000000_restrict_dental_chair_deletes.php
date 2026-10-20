<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * environments-audit / T1: an environment must not take clinical history with
 * it. The original appointments/appointment_blocks foreign keys used
 * ON DELETE CASCADE, so a hard delete of dental_chairs removed every
 * appointment (bypassing SoftDeletes) and every block of that chair.
 *
 * RESTRICT is the schema-level safety net behind the controller 409 guard: any
 * code path that tries to delete a referenced environment fails instead of
 * destroying scheduler data.
 */
return new class extends Migration
{
    private const TABLES = ['appointments', 'appointment_blocks'];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['dental_chair_id']);
            });

            Schema::table($table, function (Blueprint $table) {
                $table->foreign('dental_chair_id')
                    ->references('id')
                    ->on('dental_chairs')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['dental_chair_id']);
            });

            Schema::table($table, function (Blueprint $table) {
                $table->foreign('dental_chair_id')
                    ->references('id')
                    ->on('dental_chairs')
                    ->onDelete('cascade');
            });
        }
    }
};
