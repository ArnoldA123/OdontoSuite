<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #293 S1/S2 — the plan line names the site its procedure applies to.
 *
 * `tooth_surface_id` already hangs from its piece, so the surface is the whole
 * address of a surface procedure; `site_code` carries the quadrant or arch
 * vocabulary declared by `procedure_catalog.site_type`. Both are nullable at
 * the schema level: only the procedure's declaration makes them mandatory, and
 * that rule is enforced by App\Support\ProcedureSiteRule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->foreignId('tooth_surface_id')
                ->nullable()
                ->after('dental_piece_id')
                ->constrained('tooth_surfaces')
                ->nullOnDelete();
            $table->string('site_code', 30)->nullable()->after('tooth_surface_id');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tooth_surface_id');
            $table->dropColumn('site_code');
        });
    }
};
