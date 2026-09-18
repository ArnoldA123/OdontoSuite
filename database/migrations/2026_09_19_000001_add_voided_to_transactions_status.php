<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `transactions` MODIFY `status` ENUM('pending','completed','failed','cancelled','voided') NOT NULL DEFAULT 'pending'"
            );

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('transactions', function ($table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `transactions` MODIFY `status` ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
