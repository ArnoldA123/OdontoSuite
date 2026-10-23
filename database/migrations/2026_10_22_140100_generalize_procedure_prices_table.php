<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One tariff table (#293 S3, S5).
     *
     * `procedure_branch_prices` only described one sede, and the group list
     * price had no window. A row here is a price agreed for a procedure with a
     * sede, with a convenio, with both, or with neither; the resolver picks the
     * most specific row in force on the reference date. Existing branch rows are
     * copied verbatim, so no quote written before this migration changes its
     * meaning.
     */
    public function up(): void
    {
        Schema::create('procedure_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_catalog_id')
                ->constrained('procedure_catalog')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->nullOnDelete();
            $table->decimal('price', 10, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'procedure_catalog_id'], 'idx_price_branch_procedure');
            $table->index(['payer_id', 'procedure_catalog_id'], 'idx_price_payer_procedure');
            $table->index(
                ['procedure_catalog_id', 'branch_id', 'payer_id', 'valid_from'],
                'idx_price_procedure_dimension_window',
            );
        });

        // Query builder only: migrations must not reference App\Models (guard
        // SddCheckMigrationsTest). The ids are preserved so the rows already
        // referenced by a snapshot keep pointing at the same tariff.
        DB::table('procedure_prices')->insertUsing(
            [
                'id',
                'procedure_catalog_id',
                'branch_id',
                'payer_id',
                'price',
                'valid_from',
                'valid_to',
                'is_active',
                'created_at',
                'updated_at',
            ],
            DB::table('procedure_branch_prices')->selectRaw(
                'id, procedure_catalog_id, branch_id, null as payer_id, price, valid_from, valid_to, is_active, created_at, updated_at',
            ),
        );

        Schema::dropIfExists('procedure_branch_prices');
    }

    public function down(): void
    {
        Schema::create('procedure_branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_catalog_id')
                ->constrained('procedure_catalog')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'procedure_catalog_id'], 'idx_branch_price_branch_procedure');
            $table->index(['procedure_catalog_id', 'branch_id', 'valid_from'], 'idx_branch_price_procedure_branch_window');
        });

        // Only the rows the old table could represent: a sede and no convenio.
        DB::table('procedure_branch_prices')->insertUsing(
            [
                'id',
                'procedure_catalog_id',
                'branch_id',
                'price',
                'valid_from',
                'valid_to',
                'is_active',
                'created_at',
                'updated_at',
            ],
            DB::table('procedure_prices')
                ->whereNotNull('branch_id')
                ->whereNull('payer_id')
                ->selectRaw('id, procedure_catalog_id, branch_id, price, valid_from, valid_to, is_active, created_at, updated_at'),
        );

        Schema::dropIfExists('procedure_prices');
    }
};
