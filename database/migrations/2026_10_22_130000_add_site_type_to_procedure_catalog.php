<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #293 S1 — the catalog declares what surface a procedure is performed on.
 *
 * `none` by default so the existing catalog keeps its contract: a procedure
 * either applies to a tooth, a tooth surface, a quadrant, an arch, or to the
 * patient as a whole. The declaration is what later lets a plan or an estimate
 * demand the site (slice C1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_catalog', function (Blueprint $table) {
            $table->string('site_type', 20)->default('none')->after('specialty_id');
            $table->index('site_type', 'idx_procedure_catalog_site_type');
        });
    }

    public function down(): void
    {
        Schema::table('procedure_catalog', function (Blueprint $table) {
            $table->dropIndex('idx_procedure_catalog_site_type');
            $table->dropColumn('site_type');
        });
    }
};
