<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->foreignId('default_procedure_catalog_id')
                ->nullable()
                ->after('price')
                ->constrained('procedure_catalog')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_procedure_catalog_id');
        });
    }
};
