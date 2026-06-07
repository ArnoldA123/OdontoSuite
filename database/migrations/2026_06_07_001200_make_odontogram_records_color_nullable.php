<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odontogram_records', function (Blueprint $table) {
            $table->string('color', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('odontogram_records', function (Blueprint $table) {
            $table->string('color', 32)->nullable(false)->change();
        });
    }
};
