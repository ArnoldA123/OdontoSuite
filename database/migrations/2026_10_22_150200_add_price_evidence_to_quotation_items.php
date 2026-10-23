<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #293 S4/S6 — the evidence of the tariff a line froze.
 *
 * `price_row_id` deliberately carries no foreign key: what the column proves is
 * which price applied on the issue date, so the evidence has to survive the
 * tariff row being retired. `price_source` names the cascade step that won
 * (`payer_branch`, `payer`, `branch`, `catalog`) and the window is the one that
 * row declared when the estimate was issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('price_source', 20)->nullable()->after('unit_price');
            $table->unsignedBigInteger('price_row_id')->nullable()->after('price_source');
            $table->date('price_valid_from')->nullable()->after('price_row_id');
            $table->date('price_valid_to')->nullable()->after('price_valid_from');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn([
                'price_source',
                'price_row_id',
                'price_valid_from',
                'price_valid_to',
            ]);
        });
    }
};
