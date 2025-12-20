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
        Schema::create('interconsultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_specialist_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('to_specialist_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->string('specialty_from', 50);
            $table->string('specialty_to', 50);
            $table->text('reason')->nullable(); // Motivo de la interconsulta
            $table->text('clinical_question')->nullable(); // Pregunta clínica específica
            $table->text('clinical_data')->nullable(); // Datos clínicos relevantes
            $table->text('requested_studies')->nullable(); // Estudios solicitados
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->text('response')->nullable(); // Respuesta del especialista
            $table->text('recommendations')->nullable(); // Recomendaciones
            $table->text('follow_up_notes')->nullable(); // Notas de seguimiento
            $table->date('requested_date');
            $table->date('response_date')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['from_specialist_id', 'status']);
            $table->index(['to_specialist_id', 'status']);
            $table->index(['specialty_from', 'specialty_to']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interconsultations');
    }
};
