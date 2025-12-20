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
        Schema::create('endodontics_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('dental_piece_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('tooth_number', 10); // Número de la pieza dental
            $table->integer('canal_count'); // Número de conductos
            $table->json('canal_lengths')->nullable(); // Longitudes de trabajo por conducto
            $table->json('canal_diameters')->nullable(); // Diámetros de conductos
            $table->string('working_length_method', 50)->nullable(); // Método de medición
            $table->text('pulp_diagnosis')->nullable(); // Diagnóstico pulpar
            $table->text('periapical_diagnosis')->nullable(); // Diagnóstico periapical
            $table->text('treatment_plan')->nullable();
            $table->text('anesthesia_used')->nullable();
            $table->text('access_cavity_notes')->nullable();
            $table->text('canal_preparation_notes')->nullable();
            $table->text('irrigation_protocol')->nullable();
            $table->text('medication_used')->nullable(); // Medicación intracanal
            $table->text('obturation_technique')->nullable();
            $table->text('obturation_materials')->nullable();
            $table->text('complications')->nullable();
            $table->json('radiographic_measurements')->nullable();
            $table->enum('treatment_status', ['in_progress', 'completed', 'failed', 'retreatment'])->default('in_progress');
            $table->date('treatment_completion_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'dental_piece_id']);
            $table->index(['appointment_id', 'treatment_status']);
            $table->index('tooth_number');
            $table->index('treatment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('endodontics_records');
    }
};
