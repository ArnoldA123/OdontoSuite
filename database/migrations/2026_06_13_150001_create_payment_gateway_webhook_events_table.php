<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->string('event_type', 100);
            $table->string('external_id', 255)->nullable();
            $table->json('payload');
            $table->string('signature', 255)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // Idempotency: mismo external_id + event_type no se reprocesa
            $table->unique(['external_id', 'event_type'], 'uk_webhook_idempotency');
            $table->index(['gateway', 'processed']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_webhook_events');
    }
};
