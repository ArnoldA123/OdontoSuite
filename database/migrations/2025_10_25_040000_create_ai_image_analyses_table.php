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
        Schema::create('ai_image_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_attachment_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('status'); // pending, processing, completed, failed
            $table->text('prompt_sent')->nullable();
            $table->json('findings')->nullable(); // diagnósticos encontrados
            $table->json('recommendations')->nullable(); // recomendaciones de tratamiento
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0-100
            $table->text('raw_response')->nullable(); // respuesta completa de la IA
            $table->string('model_used')->default('gpt-4o');
            $table->integer('tokens_used')->nullable();
            $table->boolean('reviewed')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->enum('review_decision', ['accepted', 'rejected', 'partial'])->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['clinical_attachment_id', 'status']);
            $table->index(['patient_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_image_analyses');
    }
};
