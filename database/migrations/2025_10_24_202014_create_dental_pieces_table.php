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
        Schema::create('dental_pieces', function (Blueprint $table) {
            $table->id();
            $table->string('fdi_number', 2)->unique(); // Número FDI (11-18, 21-28, 31-38, 41-48)
            $table->string('name', 50); // Nombre de la pieza (Incisivo central superior derecho, etc.)
            $table->string('type', 20); // incisor, canine, premolar, molar
            $table->string('quadrant', 20); // superior_derecho, superior_izquierdo, inferior_derecho, inferior_izquierdo
            $table->integer('position'); // Posición en el cuadrante (1-8)
            $table->boolean('is_permanent')->default(true); // true = permanente, false = temporal
            $table->text('description')->nullable();
            $table->json('surfaces')->nullable(); // Superficies disponibles para esta pieza
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['quadrant', 'position']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dental_pieces');
    }
};
