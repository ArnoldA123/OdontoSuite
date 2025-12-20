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
        Schema::create('rehabilitation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('dental_piece_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('prosthesis_type', 50); // crown, bridge, partial, complete, etc.
            $table->string('material_type', 50); // metal, ceramic, zirconia, etc.
            $table->string('laboratory_name', 100)->nullable();
            $table->string('laboratory_contact', 100)->nullable();
            $table->date('impression_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('cementation_date')->nullable();
            $table->text('shade_selection')->nullable(); // Selección de color
            $table->text('impression_notes')->nullable();
            $table->text('try_in_notes')->nullable();
            $table->text('adjustment_notes')->nullable();
            $table->text('cementation_notes')->nullable();
            $table->json('measurements')->nullable(); // Mediciones clínicas
            $table->text('patient_satisfaction')->nullable();
            $table->text('complications')->nullable();
            $table->enum('status', ['impression', 'laboratory', 'try_in', 'delivered', 'cemented', 'failed'])->default('impression');
            $table->text('follow_up_notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'dental_piece_id']);
            $table->index(['appointment_id', 'status']);
            $table->index('prosthesis_type');
            $table->index('material_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rehabilitation_records');
    }
};
