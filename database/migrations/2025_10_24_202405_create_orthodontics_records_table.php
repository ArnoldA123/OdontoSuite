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
        Schema::create('orthodontics_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('treatment_type', 50); // brackets, aligners, functional, etc.
            $table->string('appliance_type', 100)->nullable(); // Tipo de aparato
            $table->date('treatment_start_date');
            $table->date('estimated_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->enum('treatment_phase', ['initial', 'active', 'retention', 'completed'])->default('initial');
            $table->text('treatment_objectives')->nullable();
            $table->text('current_notes')->nullable();
            $table->text('activation_notes')->nullable(); // Notas de activación
            $table->json('elastic_configuration')->nullable(); // Configuración de elásticos
            $table->json('bracket_positions')->nullable(); // Posiciones de brackets
            $table->text('progress_notes')->nullable();
            $table->text('complications')->nullable();
            $table->json('measurements')->nullable(); // Mediciones ortodóncicas
            $table->text('retention_plan')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'treatment_phase']);
            $table->index(['appointment_id', 'treatment_type']);
            $table->index('treatment_start_date');
            $table->index('treatment_phase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orthodontics_records');
    }
};
