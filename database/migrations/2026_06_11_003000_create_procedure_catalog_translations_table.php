<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_catalog_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_catalog_id')->constrained('procedure_catalog')->onDelete('cascade');
            $table->string('locale', 10)->default('es'); // es, en, pt, etc.
            $table->string('name', 200)->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('materials_needed')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('post_procedure_care')->nullable();
            $table->timestamps();

            $table->unique(['procedure_catalog_id', 'locale'], 'pc_trans_unique');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_catalog_translations');
    }
};
