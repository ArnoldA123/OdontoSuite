<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Issue #246 (related note): `rehabilitation_records` has had the `try_in`
 * status since the first migration, but no column recorded when the try-in
 * happened, so the state could not be placed on a timeline.
 *
 * No backfill: the workflow dates that could hint at a try-in (impression,
 * delivery, cementation) are different clinical events, so existing rows stay
 * without a try-in date instead of inventing one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rehabilitation_records', function (Blueprint $table) {
            $table->date('try_in_date')->nullable()->after('impression_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rehabilitation_records', function (Blueprint $table) {
            $table->dropColumn('try_in_date');
        });
    }
};
