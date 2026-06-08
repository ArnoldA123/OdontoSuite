<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->foreignId('procedure_catalog_id')
                ->nullable()
                ->after('procedure_id')
                ->constrained('procedure_catalog')
                ->nullOnDelete();

            $table->index('procedure_catalog_id', 'idx_tpi_procedure_catalog');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropForeign(['procedure_catalog_id']);
            $table->dropIndex('idx_tpi_procedure_catalog');
            $table->dropColumn('procedure_catalog_id');
        });
    }
};
