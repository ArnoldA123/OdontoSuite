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
        Schema::create('clinical_evolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('medical_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->date('evolution_date');
            $table->string('specialty', 50)->nullable(); // Especialidad del profesional
            $table->text('subjective')->nullable(); // Subjetivo (síntomas del paciente)
            $table->text('objective')->nullable(); // Objetivo (hallazgos del examen)
            $table->text('assessment')->nullable(); // Evaluación (diagnóstico)
            $table->text('plan')->nullable(); // Plan (tratamiento)
            $table->text('procedures_performed')->nullable(); // Procedimientos realizados
            $table->text('materials_used')->nullable(); // Materiales utilizados
            $table->text('prescriptions')->nullable(); // Recetas médicas
            $table->text('recommendations')->nullable(); // Recomendaciones
            $table->text('next_appointment_notes')->nullable(); // Notas para próxima cita
            $table->json('vital_signs')->nullable(); // Signos vitales en esta evolución
            $table->json('clinical_measurements')->nullable(); // Mediciones clínicas
            $table->boolean('requires_follow_up')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'evolution_date']);
            $table->index(['appointment_id', 'evolution_date']);
            $table->index(['medical_record_id', 'evolution_date']);
            $table->index('specialty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_evolutions');
    }
};
