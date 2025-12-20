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
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['income', 'expense', 'withdrawal', 'deposit', 'adjustment'])->default('income');
            $table->decimal('amount', 10, 2);
            $table->string('description', 200);
            $table->text('notes')->nullable();
            $table->string('reference', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['cash_register_session_id', 'type']);
            $table->index(['transaction_id', 'type']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
