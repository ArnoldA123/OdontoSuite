<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branch tariff (#290 S8, decision L6).
     *
     * `procedure_catalog.default_cost` stays the group list price; a row here
     * is the price agreed for one sede, optionally inside a validity window so a
     * promotion in a low-occupancy branch never leaks to another city. Several
     * windows may exist for the same pair; the resolver picks the one in force
     * at the reference date, latest `valid_from` first.
     */
    public function up(): void
    {
        Schema::create('procedure_branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_catalog_id')
                ->constrained('procedure_catalog')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'procedure_catalog_id'], 'idx_branch_price_branch_procedure');
            $table->index(['procedure_catalog_id', 'branch_id', 'valid_from'], 'idx_branch_price_procedure_branch_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_branch_prices');
    }
};
