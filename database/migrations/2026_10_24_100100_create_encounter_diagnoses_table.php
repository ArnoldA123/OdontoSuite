<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * F6 (#286) — the coded diagnosis of one act.
 *
 * NTS 139 asks for the diagnosis with its CIE-10 code, so the act names rows of
 * `diagnosis_catalog` instead of a free text. The code and the name are copied
 * at the close: editing the catalogue never rewrites a signed record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_encounter_id')
                ->constrained('clinical_encounters')
                ->cascadeOnDelete();
            $table->foreignId('diagnosis_catalog_id')
                ->constrained('diagnosis_catalog')
                ->restrictOnDelete();
            $table->string('cie_code', 10);
            $table->string('name', 200);
            $table->string('type', 20)->default('definitive');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['clinical_encounter_id', 'cie_code'], 'encounter_diagnoses_encounter_code_unique');
            $table->index('cie_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_diagnoses');
    }
};
