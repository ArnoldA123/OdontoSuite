<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 2 fix: la migration 2026_06_13_140000 definio gateway_config
 * como JSON. Pero Crypt::encryptString produce un string base64
 * (no JSON valido), y MySQL JSON columns rechazan valores no-JSON.
 * Fix: cambiar a text para que acepte el encrypted string.
 *
 * Usamos DB::statement directo (sin doctrine/dbal) para el alter.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payment_methods MODIFY gateway_config TEXT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payment_methods MODIFY gateway_config JSON NULL");
    }
};
