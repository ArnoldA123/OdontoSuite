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
        Schema::create('tooth_surfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dental_piece_id')->constrained()->onDelete('cascade');
            $table->string('surface_code', 10); // M, D, V, L, O, I, etc.
            $table->string('surface_name', 50); // Mesial, Distal, Vestibular, etc.
            $table->string('abbreviation', 5); // M, D, V, L, O, I
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dental_piece_id', 'surface_code']);
            $table->index('surface_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tooth_surfaces');
    }
};
