<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * #295 (S8) — stock and consumption quantities keep three decimals.
 *
 * The columns were INTEGER while `products.unit` already accepted kg and ml, so
 * `0.5` entered as `1` (MySQL rounds the decimal literal silently) and the
 * ledger could not represent what the shelf actually holds. `decimal(12,3)`
 * keeps sub-unit quantities exact: 0.5 syringe, 12.5 ml, 1.250 kg.
 *
 * `down()` truncates to whole units, which is the precision the old schema had:
 * a rollback is a data-lossy operation and is documented as such.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock_quantity', 12, 3)->default(0)->change();
            $table->decimal('minimum_stock', 12, 3)->default(0)->change();
            $table->decimal('maximum_stock', 12, 3)->nullable()->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('previous_stock', 12, 3)->change();
            $table->decimal('new_stock', 12, 3)->change();
        });

        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->decimal('quantity_used', 12, 3)->change();
        });

        // MySQL keeps the stored integers on widening, and MariaDB does too;
        // the explicit cast documents that nothing is lost in this direction.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('UPDATE products SET stock_quantity = CAST(stock_quantity AS DECIMAL(12,3))');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->change();
            $table->integer('minimum_stock')->default(0)->change();
            $table->integer('maximum_stock')->nullable()->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('previous_stock')->change();
            $table->integer('new_stock')->change();
        });

        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->integer('quantity_used')->change();
        });
    }
};
