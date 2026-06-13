<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 0 (B-CASH-2): agregar columna is_system a payment_methods.
 * Razon: distinguir metodos del sistema (cash, transfer, credit_card) que
 * toda clinica peruana usa y no deben ser borrados por el admin, de
 * metodos custom (Yape del banco X, Plin, etc.) que si pueden eliminarse.
 * La columna is_active ya existe para soft-disable, is_system es la
 * proteccion contra delete destructivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
