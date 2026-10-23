<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plan_item_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->onDelete('cascade');
            $table->foreignId('treatment_plan_item_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->foreignId('consumed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('consumed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['clinical_encounter_id', 'treatment_plan_item_id'],
                'unique_encounter_plan_item'
            );
            $table->index(['treatment_plan_item_id', 'consumed_at'], 'tpic_item_consumed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_item_consumptions');
    }
};
