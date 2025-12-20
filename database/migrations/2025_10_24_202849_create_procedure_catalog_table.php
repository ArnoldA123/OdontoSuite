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
        Schema::create('procedure_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('specialty', 50)->nullable();
            $table->integer('default_duration_minutes')->default(30);
            $table->decimal('default_cost', 10, 2)->default(0);
            $table->text('requirements')->nullable(); // Requisitos previos
            $table->text('materials_needed')->nullable(); // Materiales necesarios
            $table->boolean('requires_anesthesia')->default(false);
            $table->boolean('requires_radiographs')->default(false);
            $table->json('steps')->nullable(); // Pasos del procedimiento
            $table->text('contraindications')->nullable();
            $table->text('post_procedure_care')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('specialty');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedure_catalog');
    }
};
