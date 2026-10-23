<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #295 (S4) — the procedure declares the inputs it consumes.
 *
 * `procedure_materials` records what an act *did* consume; this table records
 * what a procedure is *expected* to consume. The two are deliberately
 * different rows: the recipe is catalog data an administrator edits, and the
 * act keeps its own adjustment (the professional may use more or less).
 *
 * `products` has no sede: stock is one balance for the clinic, so the recipe
 * needs no branch dimension either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_catalog_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_catalog_id')->constrained('procedure_catalog')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Twelve integer digits and three decimals: same scale as the stock
            // balance it feeds, so 0.5 syringe and 12.5 ml are valid.
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            // A procedure declares each product once; two lots of the same
            // input are two lines of the act, not two lines of the recipe.
            // Explicit short name: the generated one exceeds MySQL's 64 chars.
            $table->unique(['procedure_catalog_id', 'product_id'], 'procedure_catalog_materials_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_catalog_materials');
    }
};
