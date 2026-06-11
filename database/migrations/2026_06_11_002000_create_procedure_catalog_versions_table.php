<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_catalog_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_catalog_id')->constrained('procedure_catalog')->onDelete('cascade');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('change_type', 50); // 'created', 'updated', 'deactivated'
            $table->json('changed_fields')->nullable(); // ['default_cost' => 50.00, 'name' => 'X']
            $table->decimal('default_cost', 10, 2)->nullable();
            $table->string('name', 200)->nullable();
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['procedure_catalog_id', 'changed_at']);
            $table->index('change_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_catalog_versions');
    }
};
