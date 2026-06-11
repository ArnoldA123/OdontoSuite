<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 (M-1): agregar SoftDeletes a CashRegisterSession.
 * Tabla: cash_register_sessions
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
