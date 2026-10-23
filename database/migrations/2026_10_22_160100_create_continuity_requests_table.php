<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuity_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('kind', ['next_session', 'control', 'recall'])->default('next_session');
            $table->enum('status', ['pending', 'scheduled', 'dismissed'])->default('pending');
            $table->dateTime('preferred_from')->nullable();
            $table->dateTime('preferred_to')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('scheduled_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'preferred_from']);
            $table->index(['patient_id', 'status']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuity_requests');
    }
};
