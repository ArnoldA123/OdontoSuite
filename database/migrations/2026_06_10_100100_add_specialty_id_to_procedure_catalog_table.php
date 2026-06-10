<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_catalog', function (Blueprint $table) {
            $table->foreignId('specialty_id')
                ->nullable()
                ->after('specialty')
                ->constrained('specialties')
                ->nullOnDelete();

            $table->index('specialty_id');
        });

        DB::statement("
            UPDATE procedure_catalog pc
            INNER JOIN specialties s ON s.code = pc.specialty
            SET pc.specialty_id = s.id
            WHERE pc.specialty IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('procedure_catalog', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
};
