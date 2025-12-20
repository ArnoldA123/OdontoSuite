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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('receipt_number', 20)->unique();
            $table->date('receipt_date');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_method', 50);
            $table->string('reference_number', 50)->nullable();
            $table->json('items')->nullable(); // Detalle de items del comprobante
            $table->enum('status', ['draft', 'issued', 'cancelled'])->default('draft');
            $table->timestamps();

            $table->index(['patient_id', 'receipt_date']);
            $table->index(['transaction_id', 'status']);
            $table->index('receipt_number');
            $table->index('receipt_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
