<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Agregar restricciones únicas para evitar duplicados
            $table->unique('email', 'patients_email_unique');
            $table->unique('phone', 'patients_phone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Eliminar restricciones únicas
            $table->dropUnique('patients_email_unique');
            $table->dropUnique('patients_phone_unique');
        });
    }
};
