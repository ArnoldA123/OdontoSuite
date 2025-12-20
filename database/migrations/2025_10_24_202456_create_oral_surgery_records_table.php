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
        Schema::create('oral_surgery_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('dental_piece_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('procedure_type', 50); // extraction, implant, biopsy, etc.
            $table->string('surgery_site', 100); // Sitio quirúrgico
            $table->text('surgical_technique')->nullable();
            $table->text('anesthesia_type')->nullable();
            $table->text('anesthesia_amount')->nullable();
            $table->time('surgery_start_time')->nullable();
            $table->time('surgery_end_time')->nullable();
            $table->integer('surgery_duration_minutes')->nullable();
            $table->text('surgical_notes')->nullable();
            $table->text('complications')->nullable();
            $table->text('sutures_used')->nullable();
            $table->integer('suture_count')->nullable();
            $table->text('hemostasis_method')->nullable();
            $table->text('post_surgical_instructions')->nullable();
            $table->text('medications_prescribed')->nullable();
            $table->json('vital_signs')->nullable();
            $table->text('recovery_notes')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'complications'])->default('scheduled');
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'procedure_type']);
            $table->index(['appointment_id', 'status']);
            $table->index('surgery_site');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oral_surgery_records');
    }
};
