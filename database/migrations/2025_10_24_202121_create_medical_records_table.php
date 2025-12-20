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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('record_number', 20)->unique();
            $table->date('first_visit_date');
            $table->text('chief_complaint')->nullable(); // Motivo de consulta
            $table->text('medical_history')->nullable(); // Antecedentes médicos
            $table->text('dental_history')->nullable(); // Antecedentes odontológicos
            $table->text('allergies')->nullable();
            $table->text('medications')->nullable(); // Medicamentos actuales
            $table->text('systemic_conditions')->nullable(); // Condiciones sistémicas
            $table->text('family_history')->nullable(); // Antecedentes familiares
            $table->text('social_history')->nullable(); // Antecedentes sociales
            $table->json('vital_signs')->nullable(); // Signos vitales
            $table->text('clinical_examination')->nullable(); // Examen clínico
            $table->text('diagnosis')->nullable(); // Diagnóstico
            $table->text('treatment_plan')->nullable(); // Plan de tratamiento
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['patient_id', 'is_active']);
            $table->index('record_number');
            $table->index('first_visit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
