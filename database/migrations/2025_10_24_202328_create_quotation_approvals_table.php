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
        Schema::create('quotation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'modified'])->default('pending');
            $table->text('patient_notes')->nullable(); // Comentarios del paciente
            $table->text('modifications_requested')->nullable(); // Modificaciones solicitadas
            $table->date('approval_date')->nullable();
            $table->string('approval_method', 50)->nullable(); // email, in_person, phone, etc.
            $table->text('signature_data')->nullable(); // Datos de firma digital
            $table->json('approved_items')->nullable(); // Items específicos aprobados
            $table->timestamps();

            $table->index(['quotation_id', 'approval_status']);
            $table->index(['patient_id', 'approval_status']);
            $table->index('approval_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_approvals');
    }
};
