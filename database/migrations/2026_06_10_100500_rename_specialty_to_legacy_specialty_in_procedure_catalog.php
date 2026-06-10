<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_catalog', function (Blueprint $table) {
            $table->renameColumn('specialty', 'legacy_specialty');
        });
    }

    public function down(): void
    {
        Schema::table('procedure_catalog', function (Blueprint $table) {
            $table->renameColumn('legacy_specialty', 'specialty');
        });
    }
};
