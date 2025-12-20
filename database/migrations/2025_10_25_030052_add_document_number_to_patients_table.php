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
            $table->string('document_number', 20)->nullable()->after('last_name');
        });

        // Actualizar pacientes existentes con document_number basado en su ID
        $patients = \App\Models\Patient::whereNull('document_number')->orWhere('document_number', '')->get();
        foreach ($patients as $patient) {
            $patient->update(['document_number' => 'DOC-' . str_pad($patient->id, 8, '0', STR_PAD_LEFT)]);
        }

        // Ahora hacer la columna unique
        Schema::table('patients', function (Blueprint $table) {
            $table->string('document_number', 20)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('document_number');
        });
    }
};
