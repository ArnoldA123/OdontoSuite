<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('version_number');
            $table->enum('status', ['draft', 'accepted', 'superseded'])->default('draft');
            $table->json('snapshot');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['treatment_plan_id', 'version_number'], 'tpv_plan_version_unique');
            $table->index(['treatment_plan_id', 'status'], 'tpv_plan_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_versions');
    }
};
