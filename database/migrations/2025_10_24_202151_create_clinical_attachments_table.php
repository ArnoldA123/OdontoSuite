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
        Schema::create('clinical_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('clinical_evolution_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('file_name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_type', 50); // image, pdf, document, xray, photo
            $table->string('mime_type', 100);
            $table->bigInteger('file_size'); // en bytes
            $table->string('category', 50); // radiografia, foto_clinica, documento, etc.
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Metadatos adicionales (dimensiones, etc.)
            $table->boolean('is_private')->default(false); // Solo visible para el profesional
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['patient_id', 'category']);
            $table->index(['appointment_id', 'file_type']);
            $table->index(['clinical_evolution_id', 'file_type']);
            $table->index('file_type');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_attachments');
    }
};
