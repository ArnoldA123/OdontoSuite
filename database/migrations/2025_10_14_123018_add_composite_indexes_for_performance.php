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
        // Add composite indexes for appointments table
        Schema::table('appointments', function (Blueprint $table) {
            // Index for filtering appointments by user and date range
            $table->index(['user_id', 'scheduled_at', 'status'], 'idx_appointments_user_date_status');

            // Index for filtering appointments by patient and date
            $table->index(['patient_id', 'scheduled_at'], 'idx_appointments_patient_date');

            // Index for filtering appointments by dental chair and date
            $table->index(['dental_chair_id', 'scheduled_at', 'status'], 'idx_appointments_chair_date_status');

            // Index for dashboard statistics queries
            $table->index(['scheduled_at', 'status'], 'idx_appointments_date_status');

            // Index for appointment type filtering
            $table->index(['appointment_type_id', 'scheduled_at'], 'idx_appointments_type_date');
        });

        // Add composite indexes for patients table
        Schema::table('patients', function (Blueprint $table) {
            // Index for active patients search
            $table->index(['is_active', 'last_name', 'first_name'], 'idx_patients_active_name');

            // Index for patient search by email and phone
            $table->index(['email', 'is_active'], 'idx_patients_email_active');
            $table->index(['phone', 'is_active'], 'idx_patients_phone_active');
        });

        // Add composite indexes for users table
        Schema::table('users', function (Blueprint $table) {
            // Index for active professionals
            $table->index(['role', 'is_active'], 'idx_users_role_active');

            // Index for user search
            $table->index(['name', 'role'], 'idx_users_name_role');
        });

        // Add composite indexes for dental_chairs table
        Schema::table('dental_chairs', function (Blueprint $table) {
            // Index for active chairs
            $table->index(['is_active', 'status'], 'idx_chairs_active_status');
        });

        // Add composite indexes for appointment_types table
        Schema::table('appointment_types', function (Blueprint $table) {
            // Index for active appointment types
            $table->index(['is_active', 'name'], 'idx_appointment_types_active_name');
        });

        // Add composite indexes for work_schedules table
        Schema::table('work_schedules', function (Blueprint $table) {
            // Index for user work schedules
            $table->index(['user_id', 'day_of_week', 'is_active'], 'idx_work_schedules_user_day_active');
        });

        // Add composite indexes for appointment_blocks table
        Schema::table('appointment_blocks', function (Blueprint $table) {
            // Index for user blocks by date range
            $table->index(['user_id', 'starts_at', 'ends_at', 'is_active'], 'idx_blocks_user_date_active');

            // Index for chair blocks by date range
            $table->index(['dental_chair_id', 'starts_at', 'ends_at', 'is_active'], 'idx_blocks_chair_date_active');
        });

        // Add composite indexes for waiting_lists table
        Schema::table('waiting_lists', function (Blueprint $table) {
            // Index for active waiting lists by priority
            $table->index(['status', 'priority', 'created_at'], 'idx_waiting_lists_status_priority');

            // Index for waiting lists by appointment type
            $table->index(['appointment_type_id', 'status'], 'idx_waiting_lists_type_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop composite indexes for appointments table
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_user_date_status');
            $table->dropIndex('idx_appointments_patient_date');
            $table->dropIndex('idx_appointments_chair_date_status');
            $table->dropIndex('idx_appointments_date_status');
            $table->dropIndex('idx_appointments_type_date');
        });

        // Drop composite indexes for patients table
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('idx_patients_active_name');
            $table->dropIndex('idx_patients_email_active');
            $table->dropIndex('idx_patients_phone_active');
        });

        // Drop composite indexes for users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_active');
            $table->dropIndex('idx_users_name_role');
        });

        // Drop composite indexes for dental_chairs table
        Schema::table('dental_chairs', function (Blueprint $table) {
            $table->dropIndex('idx_chairs_active_status');
        });

        // Drop composite indexes for appointment_types table
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropIndex('idx_appointment_types_active_name');
        });

        // Drop composite indexes for work_schedules table
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_work_schedules_user_day_active');
        });

        // Drop composite indexes for appointment_blocks table
        Schema::table('appointment_blocks', function (Blueprint $table) {
            $table->dropIndex('idx_blocks_user_date_active');
            $table->dropIndex('idx_blocks_chair_date_active');
        });

        // Drop composite indexes for waiting_lists table
        Schema::table('waiting_lists', function (Blueprint $table) {
            $table->dropIndex('idx_waiting_lists_status_priority');
            $table->dropIndex('idx_waiting_lists_type_status');
        });
    }
};
