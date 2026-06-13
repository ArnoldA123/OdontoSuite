<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('gateway', 30); // mercadopago
            $table->string('external_id', 255)->nullable(); // preference_id o payment_id
            $table->string('external_status', 50)->default('pending'); // pending/approved/rejected/cancelled
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PEN');
            $table->string('payer_email', 255)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('webhook_received_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'external_id']);
            $table->index('external_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_transactions');
    }
};
