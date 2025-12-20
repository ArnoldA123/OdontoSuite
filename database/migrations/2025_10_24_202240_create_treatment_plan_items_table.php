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
        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('procedure_id')->nullable();
            $table->foreignId('dental_piece_id')->nullable()->constrained()->onDelete('set null');
            $table->string('procedure_name', 200);
            $table->text('procedure_description')->nullable();
            $table->string('specialty', 50)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->integer('estimated_duration_minutes')->nullable();
            $table->integer('phase_number')->default(1); // Fase del tratamiento
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('materials_required')->nullable(); // Materiales necesarios
            $table->boolean('requires_anesthesia')->default(false);
            $table->boolean('is_optional')->default(false); // Procedimiento opcional
            $table->timestamps();

            $table->index(['treatment_plan_id', 'phase_number']);
            $table->index(['procedure_id', 'status']);
            $table->index(['dental_piece_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_items');
    }
};
