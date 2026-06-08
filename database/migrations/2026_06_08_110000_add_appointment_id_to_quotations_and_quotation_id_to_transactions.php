<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('appointment_id')
                ->nullable()
                ->after('treatment_plan_id')
                ->constrained('appointments')
                ->nullOnDelete();

            $table->index('appointment_id', 'idx_quotations_appointment');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('quotation_id')
                ->nullable()
                ->after('treatment_plan_id')
                ->constrained('quotations')
                ->nullOnDelete();

            $table->index('quotation_id', 'idx_transactions_quotation');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropIndex('idx_transactions_quotation');
            $table->dropColumn('quotation_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropIndex('idx_quotations_appointment');
            $table->dropColumn('appointment_id');
        });
    }
};
