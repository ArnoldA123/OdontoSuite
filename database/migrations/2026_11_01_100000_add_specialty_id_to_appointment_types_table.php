<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A type declares the specialty it belongs to. Nullable on purpose: the
     * types already stored keep no specialty, which is a valid visible state,
     * and no backfill guesses one from the name.
     */
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->foreignId('specialty_id')
                ->nullable()
                ->after('name')
                ->constrained('specialties')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
};
