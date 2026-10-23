<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F4 (#284, S1/S4) — a recall is its own entity, not a reminder.
     *
     * Its due date (`due_date`) is the contract, so the reception queue and the
     * compliance metric measure the recall itself instead of the delivery of a
     * message. `scheduled_appointment_id` links the control appointment that a
     * pending recall materialises through the agenda door.
     */
    public function up(): void
    {
        Schema::create('patient_recalls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('interval_months');
            $table->string('reason', 255);
            $table->date('due_date');
            $table->enum('status', ['pending', 'scheduled', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('scheduled_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['patient_id', 'due_date']);
            $table->index(['status', 'due_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_recalls');
    }
};
