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
        // Índices para appointments
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['branch_id', 'scheduled_at', 'status'], 'idx_appointments_branch_date_status');
            $table->index(['dental_chair_id', 'scheduled_at'], 'idx_appointments_chair_date');
            $table->index(['procedure_id', 'scheduled_at'], 'idx_appointments_procedure_date');
        });

        // Índices para transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['patient_id', 'type', 'status'], 'idx_transactions_patient_type_status');
            $table->index(['appointment_id', 'status'], 'idx_transactions_appointment_status');
            $table->index(['treatment_plan_id', 'status'], 'idx_transactions_plan_status');
            $table->index(['payment_method_id', 'processed_at'], 'idx_transactions_method_processed');
        });

        // Índices para treatment_plans
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'idx_treatment_plans_patient_status');
            $table->index(['created_by', 'status'], 'idx_treatment_plans_creator_status');
            $table->index(['start_date', 'end_date'], 'idx_treatment_plans_date_range');
        });

        // Índices para clinical_evolutions
        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->index(['patient_id', 'evolution_date'], 'idx_evolutions_patient_date');
            $table->index(['appointment_id', 'specialty'], 'idx_evolutions_appointment_specialty');
            $table->index(['medical_record_id', 'evolution_date'], 'idx_evolutions_record_date');
        });

        // Índices para odontogram_records
        Schema::table('odontogram_records', function (Blueprint $table) {
            $table->index(['odontogram_id', 'dental_piece_id'], 'idx_odontogram_records_odontogram_piece');
            $table->index(['dental_piece_id', 'condition_code'], 'idx_odontogram_records_piece_condition');
            $table->index(['appointment_id', 'created_at'], 'idx_odontogram_records_appointment_created');
        });

        // Índices para stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'branch_id', 'type'], 'idx_stock_movements_product_branch_type');
            $table->index(['branch_id', 'movement_date', 'type'], 'idx_stock_movements_branch_date_type');
            $table->index(['created_by', 'movement_date'], 'idx_stock_movements_creator_date');
        });

        // Índices para cash_register_sessions
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'opened_at'], 'idx_cash_sessions_user_status_opened');
            $table->index(['branch_id', 'opened_at'], 'idx_cash_sessions_branch_opened');
        });

        // Índices para medical_records
        Schema::table('medical_records', function (Blueprint $table) {
            $table->index(['patient_id', 'is_active'], 'idx_medical_records_patient_active');
            $table->index(['first_visit_date', 'is_active'], 'idx_medical_records_visit_date_active');
        });

        // Índices para interconsultations
        Schema::table('interconsultations', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'idx_interconsultations_patient_status');
            $table->index(['from_specialist_id', 'status'], 'idx_interconsultations_from_status');
            $table->index(['to_specialist_id', 'status'], 'idx_interconsultations_to_status');
            $table->index(['specialty_from', 'specialty_to'], 'idx_interconsultations_specialties');
        });

        // Índices para payment_plans
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'idx_payment_plans_patient_status');
            $table->index(['treatment_plan_id', 'status'], 'idx_payment_plans_plan_status');
            $table->index(['start_date', 'end_date'], 'idx_payment_plans_date_range');
        });

        // Índices para installments
        Schema::table('installments', function (Blueprint $table) {
            $table->index(['payment_plan_id', 'status'], 'idx_installments_plan_status');
            $table->index(['due_date', 'status'], 'idx_installments_due_status');
        });

        // Índices para clinical_attachments
        Schema::table('clinical_attachments', function (Blueprint $table) {
            $table->index(['patient_id', 'file_type'], 'idx_attachments_patient_type');
            $table->index(['appointment_id', 'category'], 'idx_attachments_appointment_category');
            $table->index(['clinical_evolution_id', 'file_type'], 'idx_attachments_evolution_type');
        });

        // Índices para procedure_materials
        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->index(['appointment_id', 'product_id'], 'idx_procedure_materials_appointment_product');
            $table->index(['product_id', 'batch_number'], 'idx_procedure_materials_product_batch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover índices de appointments
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_branch_date_status');
            $table->dropIndex('idx_appointments_chair_date');
            $table->dropIndex('idx_appointments_procedure_date');
        });

        // Remover índices de transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_patient_type_status');
            $table->dropIndex('idx_transactions_appointment_status');
            $table->dropIndex('idx_transactions_plan_status');
            $table->dropIndex('idx_transactions_method_processed');
        });

        // Remover índices de treatment_plans
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropIndex('idx_treatment_plans_patient_status');
            $table->dropIndex('idx_treatment_plans_creator_status');
            $table->dropIndex('idx_treatment_plans_date_range');
        });

        // Remover índices de clinical_evolutions
        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->dropIndex('idx_evolutions_patient_date');
            $table->dropIndex('idx_evolutions_appointment_specialty');
            $table->dropIndex('idx_evolutions_record_date');
        });

        // Remover índices de odontogram_records
        Schema::table('odontogram_records', function (Blueprint $table) {
            $table->dropIndex('idx_odontogram_records_odontogram_piece');
            $table->dropIndex('idx_odontogram_records_piece_condition');
            $table->dropIndex('idx_odontogram_records_appointment_created');
        });

        // Remover índices de stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_stock_movements_product_branch_type');
            $table->dropIndex('idx_stock_movements_branch_date_type');
            $table->dropIndex('idx_stock_movements_creator_date');
        });

        // Remover índices de cash_register_sessions
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_cash_sessions_user_status_opened');
            $table->dropIndex('idx_cash_sessions_branch_opened');
        });

        // Remover índices de medical_records
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex('idx_medical_records_patient_active');
            $table->dropIndex('idx_medical_records_visit_date_active');
        });

        // Remover índices de interconsultations
        Schema::table('interconsultations', function (Blueprint $table) {
            $table->dropIndex('idx_interconsultations_patient_status');
            $table->dropIndex('idx_interconsultations_from_status');
            $table->dropIndex('idx_interconsultations_to_status');
            $table->dropIndex('idx_interconsultations_specialties');
        });

        // Remover índices de payment_plans
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->dropIndex('idx_payment_plans_patient_status');
            $table->dropIndex('idx_payment_plans_plan_status');
            $table->dropIndex('idx_payment_plans_date_range');
        });

        // Remover índices de installments
        Schema::table('installments', function (Blueprint $table) {
            $table->dropIndex('idx_installments_plan_status');
            $table->dropIndex('idx_installments_due_status');
        });

        // Remover índices de clinical_attachments
        Schema::table('clinical_attachments', function (Blueprint $table) {
            $table->dropIndex('idx_attachments_patient_type');
            $table->dropIndex('idx_attachments_appointment_category');
            $table->dropIndex('idx_attachments_evolution_type');
        });

        // Remover índices de procedure_materials
        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->dropIndex('idx_procedure_materials_appointment_product');
            $table->dropIndex('idx_procedure_materials_product_batch');
        });
    }
};
