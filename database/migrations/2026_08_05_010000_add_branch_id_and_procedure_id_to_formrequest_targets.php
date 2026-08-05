<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 02 / T-02.7, T-02.9 — additive-only nullable columns to support the
 * expanded FormRequests:
 *
 *  - treatment_plans.branch_id   → FK branches (nullable)
 *  - cash_movements.branch_id    → FK branches (nullable)
 *  - cash_movements.concept      → ENUM whitelist (nullable to allow legacy rows)
 *  - quotations.procedure_id     → FK procedure_catalog (nullable)
 *  - quotations.payment_method_id → FK payment_methods (nullable)
 *
 * All columns are ADD NULLABLE — safe to rollback via `git revert`
 * (no destructive DROP on existing data, the new FK constraint uses
 * `onDelete SET NULL`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('branches')
                ->onDelete('set null');
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('cash_register_session_id')
                ->constrained('branches')
                ->onDelete('set null');
            $table->string('concept', 50)
                ->nullable()
                ->after('type')
                ->comment('Whitelist: opening_balance|sale_refund|withdrawal|deposit|adjustment');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('procedure_id')
                ->nullable()
                ->after('treatment_plan_id')
                ->constrained('procedure_catalog')
                ->onDelete('set null');
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('procedure_id')
                ->constrained('payment_methods')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropForeign(['procedure_id']);
            $table->dropColumn(['procedure_id', 'payment_method_id']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'concept']);
        });

        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id']);
        });
    }
};
