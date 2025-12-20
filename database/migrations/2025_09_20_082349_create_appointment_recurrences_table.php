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
        Schema::create('appointment_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->integer('interval_value')->default(1); // Cada X días/semanas/meses
            $table->json('days_of_week')->nullable(); // Para frecuencia semanal
            $table->integer('day_of_month')->nullable(); // Para frecuencia mensual
            $table->date('end_date')->nullable();
            $table->integer('max_occurrences')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['appointment_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_recurrences');
    }
};
