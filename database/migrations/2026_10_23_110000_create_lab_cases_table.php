<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5 (#285) — the prosthetic lab case.
 *
 * The workshop works with the patient, the piece, the job and two dates; it
 * never reads the clinical record. `status` is the five-phase machine of S2 and
 * moves only forward, through the case's own door.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('professional_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dental_piece_id')->nullable()->constrained()->nullOnDelete();
            $table->string('work');
            $table->string('material')->nullable();
            $table->date('sent_at');
            $table->date('promised_at');
            $table->enum('status', ['in_preparation', 'in_laboratory', 'try_in', 'delivered', 'placed'])
                ->default('in_preparation');
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'promised_at']);
            $table->index(['technician_id', 'status']);
            $table->index(['professional_id', 'status']);
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_cases');
    }
};
