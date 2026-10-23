<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dateTime('rejected_at')->nullable()->after('priority');
            $table->string('rejection_reason', 255)->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });
    }
};
