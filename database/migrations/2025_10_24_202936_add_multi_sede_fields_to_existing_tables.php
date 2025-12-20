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
        // Agregar branch_id a users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null')->after('id');
            $table->string('professional_license', 50)->nullable()->after('specialty');
            $table->json('specialties')->nullable()->after('professional_license');
            $table->decimal('commission_rate', 5, 2)->default(0)->after('specialties');
        });

        // Agregar branch_id a patients
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null')->after('id');
            $table->string('dni', 20)->nullable()->after('last_name');
            $table->string('blood_type', 10)->nullable()->after('gender');
            $table->string('insurance_provider', 100)->nullable()->after('blood_type');
            $table->string('insurance_number', 50)->nullable()->after('insurance_provider');
        });

        // Agregar branch_id a dental_chairs
        Schema::table('dental_chairs', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null')->after('id');
        });

        // Agregar branch_id a appointments
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'professional_license', 'specialties', 'commission_rate']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'dni', 'blood_type', 'insurance_provider', 'insurance_number']);
        });

        Schema::table('dental_chairs', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
