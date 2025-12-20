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
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('treatment_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('plan_number', 20)->unique();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('down_payment', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2);
            $table->integer('installment_count');
            $table->decimal('installment_amount', 10, 2);
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'quarterly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->text('terms_conditions')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled', 'defaulted'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['treatment_plan_id', 'status']);
            $table->index('plan_number');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
