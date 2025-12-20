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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Dentist/Professional
            $table->foreignId('dental_chair_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_type_id')->constrained()->onDelete('cascade');
            $table->datetime('scheduled_at');
            $table->datetime('ends_at');
            $table->integer('duration_minutes');
            $table->enum('status', [
                'scheduled', 'confirmed', 'in_progress', 'completed',
                'cancelled', 'no_show', 'rescheduled'
            ])->default('scheduled');
            $table->text('notes')->nullable();
            $table->text('treatment_notes')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'scheduled_at']);
            $table->index(['dental_chair_id', 'scheduled_at']);
            $table->index(['patient_id', 'scheduled_at']);
            $table->index('status');
            $table->index('scheduled_at');

            // Unique constraint to prevent double booking
            $table->unique(['user_id', 'scheduled_at', 'ends_at'], 'unique_user_time_slot');
            $table->unique(['dental_chair_id', 'scheduled_at', 'ends_at'], 'unique_chair_time_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
