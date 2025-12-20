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
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('procedure_id')->nullable()->constrained('procedure_catalog')->onDelete('set null')->after('appointment_type_id');
            $table->decimal('total_cost', 10, 2)->default(0)->after('treatment_notes');
            $table->decimal('paid_amount', 10, 2)->default(0)->after('total_cost');
            $table->decimal('balance', 10, 2)->default(0)->after('paid_amount');
            $table->boolean('requires_payment')->default(false)->after('balance');
        });

        Schema::table('appointment_types', function (Blueprint $table) {
            $table->foreignId('procedure_id')->nullable()->constrained('procedure_catalog')->onDelete('set null')->after('id');
            $table->string('specialty', 50)->nullable()->after('requires_confirmation');
            $table->boolean('requires_anesthesia')->default(false)->after('specialty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['procedure_id']);
            $table->dropColumn(['procedure_id', 'total_cost', 'paid_amount', 'balance', 'requires_payment']);
        });

        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropForeign(['procedure_id']);
            $table->dropColumn(['procedure_id', 'specialty', 'requires_anesthesia']);
        });
    }
};
