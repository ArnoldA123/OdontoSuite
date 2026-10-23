<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #293 S1/S2 — the estimate line carries the site declared by the plan (or by
 * the manual estimate), so the quotation describes the same act the plan does.
 *
 * `procedure_catalog_id` is the link the rule needs: without it a line is free
 * text and no site is required. The price-evidence columns (`price_source`,
 * `price_row_id`, the applied window) belong to the next slice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('procedure_catalog_id')
                ->nullable()
                ->after('treatment_plan_item_id')
                ->constrained('procedure_catalog')
                ->nullOnDelete();
            $table->foreignId('dental_piece_id')
                ->nullable()
                ->after('procedure_catalog_id')
                ->constrained('dental_pieces')
                ->nullOnDelete();
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
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tooth_surface_id');
            $table->dropConstrainedForeignId('dental_piece_id');
            $table->dropConstrainedForeignId('procedure_catalog_id');
            $table->dropColumn('site_code');
        });
    }
};
