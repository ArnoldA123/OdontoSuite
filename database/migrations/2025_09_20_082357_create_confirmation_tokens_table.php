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
        Schema::create('confirmation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->string('token', 64)->unique();
            $table->enum('action', ['confirm', 'reschedule', 'cancel']);
            $table->datetime('expires_at');
            $table->datetime('used_at')->nullable();
            $table->json('metadata')->nullable(); // Datos adicionales para la acción
            $table->timestamps();

            $table->index(['token', 'expires_at']);
            $table->index('appointment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confirmation_tokens');
    }
};
