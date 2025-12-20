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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer', 'expired', 'damaged'])->default('in');
            $table->integer('quantity');
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->string('reference', 50)->nullable(); // Número de referencia
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->date('movement_date');
            $table->date('expiry_date')->nullable();
            $table->string('batch_number', 50)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'branch_id', 'type']);
            $table->index(['branch_id', 'movement_date']);
            $table->index('type');
            $table->index('movement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
