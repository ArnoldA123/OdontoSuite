<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 (M-1): agregar SoftDeletes a ClinicalEvolution.
 * Tabla: clinical_evolutions
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
