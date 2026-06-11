<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 (M-1): agregar SoftDeletes a MedicalRecord.
 *
 * Razon: clinica real no debe perder pacientes, citas, historias clinicas
 * ni transacciones con un DELETE accidental. Un DELETE ahora hace soft-delete
 * (marca deleted_at) y se puede restaurar con restore(). Los queries por
 * defecto excluyen los soft-deleted (esto se puede deshacer con withTrashed()).
 *
 * Importante: queries existentes (controllers, services, dashboard) NO
 * requieren cambios. Eloquent agrega el scope automaticamente. Pero las
 * relaciones belongsTo/hasMany que apunten a estos modelos tambien filtran
 * los soft-deleted por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
