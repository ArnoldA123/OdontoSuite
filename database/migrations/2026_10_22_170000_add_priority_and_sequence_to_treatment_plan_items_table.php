<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->enum('priority', ['urgent', 'elective', 'optional'])->default('elective')->after('is_optional');
            $table->unsignedInteger('sequence')->default(1)->after('phase_number');
        });

        DB::table('treatment_plan_items')
            ->where('is_optional', true)
            ->update(['priority' => 'optional']);

        $counters = [];

        DB::table('treatment_plan_items')
            ->select(['id', 'treatment_plan_id', 'phase_number'])
            ->orderBy('id')
            ->chunk(500, function ($items) use (&$counters): void {
                foreach ($items as $item) {
                    $key = $item->treatment_plan_id.':'.$item->phase_number;
                    $counters[$key] = ($counters[$key] ?? 0) + 1;

                    DB::table('treatment_plan_items')
                        ->where('id', $item->id)
                        ->update(['sequence' => $counters[$key]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropColumn(['priority', 'sequence']);
        });
    }
};
