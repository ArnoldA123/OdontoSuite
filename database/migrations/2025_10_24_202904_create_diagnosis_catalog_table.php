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
        Schema::create('diagnosis_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('cie_code', 10)->unique(); // Código CIE-10
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('specialty', 50)->nullable();
            $table->text('symptoms')->nullable();
            $table->text('treatment_guidelines')->nullable();
            $table->text('prevention_measures')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('cie_code');
            $table->index('category');
            $table->index('specialty');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnosis_catalog');
    }
};
