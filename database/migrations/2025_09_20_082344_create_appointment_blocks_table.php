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
        Schema::create('appointment_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('dental_chair_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('reason'); // Motivo obligatorio
            $table->datetime('starts_at');
            $table->datetime('ends_at');
            $table->enum('type', ['vacation', 'maintenance', 'training', 'personal', 'other']);
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_pattern')->nullable(); // Para bloqueos recurrentes
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['user_id', 'starts_at', 'ends_at']);
            $table->index(['dental_chair_id', 'starts_at', 'ends_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_blocks');
    }
};
