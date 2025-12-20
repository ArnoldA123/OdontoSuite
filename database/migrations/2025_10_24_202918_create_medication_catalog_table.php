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
        Schema::create('medication_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('generic_name', 200)->nullable();
            $table->string('active_ingredient', 200)->nullable();
            $table->string('concentration', 50)->nullable();
            $table->string('form', 50)->nullable(); // tablet, liquid, gel, etc.
            $table->text('indications')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('dosage_guidelines')->nullable();
            $table->text('interactions')->nullable();
            $table->boolean('requires_prescription')->default(true);
            $table->boolean('is_controlled_substance')->default(false);
            $table->string('category', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('generic_name');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_catalog');
    }
};
