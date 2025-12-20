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
        Schema::create('waiting_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('preferred_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('preferred_dental_chair_id')->nullable()->constrained('dental_chairs')->onDelete('set null');
            $table->integer('priority')->default(1); // 1 = highest priority
            $table->text('preferences')->nullable(); // JSON con preferencias específicas
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'notified', 'scheduled', 'cancelled'])->default('active');
            $table->datetime('preferred_start_date')->nullable();
            $table->datetime('preferred_end_date')->nullable();
            $table->datetime('notified_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['appointment_type_id', 'status']);
            $table->index(['preferred_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waiting_lists');
    }
};
