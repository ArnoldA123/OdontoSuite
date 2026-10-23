<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('plan_number')
                ->constrained('treatment_plan_versions')->nullOnDelete();
            $table->foreignId('active_version_id')->nullable()->after('current_version_id')
                ->constrained('treatment_plan_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropConstrainedForeignId('active_version_id');
        });
    }
};
