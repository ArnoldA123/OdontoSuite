<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #293 S7 — the catalog maps to the public nomenclature.
 *
 * A procedure carries its codes in the health-sector catalog
 * (RM 550-2023/MINSA) and in the SIS tariff. `system` holds the slug of the
 * nomenclator (`minsa_rm550_2023`, `sis`, or any other payer), so a new
 * financer needs a row, not a migration. The pair (system, code) is unique per
 * procedure, and the (system, code) index answers "which procedure is this
 * public code?" — the lookup of an external referral.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_external_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_catalog_id')
                ->constrained('procedure_catalog')
                ->cascadeOnDelete();
            $table->string('system', 40);
            $table->string('code', 40);
            $table->string('description', 200)->nullable();
            $table->timestamps();

            $table->unique(
                ['procedure_catalog_id', 'system', 'code'],
                'pec_procedure_system_code_unique',
            );
            $table->index(['system', 'code'], 'pec_system_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_external_codes');
    }
};
