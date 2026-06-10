<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'specialty_id']);
            $table->index('is_primary');
        });

        DB::statement("
            INSERT INTO user_specialties (user_id, specialty_id, is_primary, created_at, updated_at)
            SELECT
                u.id,
                s.id,
                1,
                NOW(),
                NOW()
            FROM users u
            INNER JOIN specialties s ON s.code = u.specialty
            WHERE u.specialty IS NOT NULL
              AND u.specialty <> ''
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_specialties');
    }
};
