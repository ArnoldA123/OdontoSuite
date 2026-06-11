<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 (M-1): agregar SoftDeletes a Interconsultation.
 * Tabla: interconsultations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interconsultations', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('interconsultations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
