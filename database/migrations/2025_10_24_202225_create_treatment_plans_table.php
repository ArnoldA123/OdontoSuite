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
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('plan_number', 20)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'proposed', 'approved', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_cost', 10, 2)->default(0);
            $table->integer('estimated_duration_weeks')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('patient_notes')->nullable(); // Notas del paciente
            $table->json('phases')->nullable(); // Fases del tratamiento
            $table->boolean('requires_anesthesia')->default(false);
            $table->boolean('is_urgent')->default(false);
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['created_by', 'status']);
            $table->index('plan_number');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_plans');
    }
};
