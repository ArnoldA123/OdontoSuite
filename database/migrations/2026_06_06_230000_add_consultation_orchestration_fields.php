<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->boolean('requires_materials')->default(false)->after('is_active');
            $table->boolean('is_consultation_mode')->default(false)->after('requires_materials');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('treatment_plan_id')
                ->nullable()
                ->after('notes')
                ->constrained('treatment_plans')
                ->nullOnDelete();

            $table->decimal('final_amount', 12, 2)->nullable()->after('treatment_plan_id');
            $table->string('consultation_mode', 32)->nullable()->after('final_amount');
            $table->timestamp('checked_in_at')->nullable()->after('consultation_mode');
            $table->timestamp('completed_at')->nullable()->after('checked_in_at');

            $table->index(['treatment_plan_id', 'status'], 'idx_appointments_plan_status');
            $table->index('consultation_mode', 'idx_appointments_consultation_mode');
        });

        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->foreignId('treatment_plan_item_id')
                ->nullable()
                ->after('appointment_id')
                ->constrained('treatment_plan_items')
                ->nullOnDelete();
        });

        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->foreignId('origin_appointment_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('appointments')
                ->nullOnDelete();

            $table->timestamp('last_activity_at')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropForeign(['origin_appointment_id']);
            $table->dropColumn(['origin_appointment_id', 'last_activity_at']);
        });

        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->dropForeign(['treatment_plan_item_id']);
            $table->dropColumn('treatment_plan_item_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['treatment_plan_id']);
            $table->dropIndex('idx_appointments_plan_status');
            $table->dropIndex('idx_appointments_consultation_mode');
            $table->dropColumn([
                'treatment_plan_id',
                'final_amount',
                'consultation_mode',
                'checked_in_at',
                'completed_at',
            ]);
        });

        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropColumn(['requires_materials', 'is_consultation_mode']);
        });
    }
};
