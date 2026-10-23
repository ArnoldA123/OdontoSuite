<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plan_item_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_item_id')->constrained()->onDelete('cascade');
            $table->string('option_code', 2);
            $table->foreignId('procedure_catalog_id')->nullable()->constrained('procedure_catalog')->nullOnDelete();
            $table->string('procedure_name', 200);
            $table->text('procedure_description')->nullable();
            $table->foreignId('dental_piece_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tooth_surface_id')->nullable()->constrained()->nullOnDelete();
            $table->string('site_code', 30)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->enum('status', ['proposed', 'accepted', 'rejected'])->default('proposed');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['treatment_plan_item_id', 'option_code'], 'tpia_item_option_unique');
            $table->index(['treatment_plan_item_id', 'status'], 'tpia_item_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_item_alternatives');
    }
};
