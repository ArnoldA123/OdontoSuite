<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #293 S3/S4 — the convenio an estimate was issued under.
 *
 * The payer is part of the price: the cascade resolves against it and the line
 * keeps the row that applied. Storing it on the quotation is what lets a later
 * reader explain why the estimate froze the price it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('payer_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('payers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payer_id');
        });
    }
};
