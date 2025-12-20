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
        Schema::create('implantology_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('dental_piece_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('implant_brand', 100);
            $table->string('implant_model', 100);
            $table->string('implant_diameter', 20); // Diámetro del implante
            $table->string('implant_length', 20); // Longitud del implante
            $table->string('batch_number', 50); // Número de lote
            $table->string('serial_number', 50)->nullable(); // Número de serie
            $table->date('placement_date');
            $table->date('healing_date')->nullable(); // Fecha de cicatrización
            $table->date('loading_date')->nullable(); // Fecha de carga
            $table->enum('status', ['placed', 'healing', 'loaded', 'failed', 'removed'])->default('placed');
            $table->text('surgical_notes')->nullable();
            $table->text('post_surgical_notes')->nullable();
            $table->text('complications')->nullable();
            $table->json('radiographic_data')->nullable(); // Datos radiográficos
            $table->json('measurements')->nullable(); // Mediciones clínicas
            $table->decimal('torque_value', 5, 2)->nullable(); // Valor de torque
            $table->text('follow_up_notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'dental_piece_id']);
            $table->index(['appointment_id', 'status']);
            $table->index(['batch_number', 'serial_number']);
            $table->index('placement_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('implantology_records');
    }
};
