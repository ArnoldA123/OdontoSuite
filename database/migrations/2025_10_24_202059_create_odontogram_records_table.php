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
        Schema::create('odontogram_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odontogram_id')->constrained()->onDelete('cascade');
            $table->foreignId('dental_piece_id')->constrained()->onDelete('cascade');
            $table->foreignId('tooth_surface_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('condition_code', 10); // C, O, X, etc. (Caries, Obturación, Extracción)
            $table->string('condition_name', 50); // Nombre de la condición
            $table->text('diagnosis')->nullable();
            $table->text('treatment_notes')->nullable();
            $table->string('color', 7)->default('#000000'); // Color para representación visual
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['odontogram_id', 'dental_piece_id']);
            $table->index(['dental_piece_id', 'tooth_surface_id']);
            $table->index('condition_code');
            $table->index('appointment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odontogram_records');
    }
};
