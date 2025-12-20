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
        Schema::table('dental_chairs', function (Blueprint $table) {
            $table->text('equipment')->nullable()->after('description');
            $table->string('status')->default('active')->after('equipment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dental_chairs', function (Blueprint $table) {
            $table->dropColumn(['equipment', 'status']);
        });
    }
};
