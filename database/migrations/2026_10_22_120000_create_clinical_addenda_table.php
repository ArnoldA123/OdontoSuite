<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * F3 (#292) — a clinical correction is an addendum, not an edit.
 *
 * The history and its evolutions are evidence of what a professional documented
 * when the patient was in front of them. Amending the row destroys that
 * evidence; the legal correction is a dated, authored, reasoned note that sits
 * next to the original. This table keeps those notes and points at the history
 * (`medical_record_id`, always) and, when the correction belongs to one visit,
 * at that evolution (`clinical_evolution_id`).
 *
 * Append-only by design: there is no `updated_at`, both foreign keys restrict
 * deletion, and the model refuses `updating` and `deleting`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinical_evolution_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 255);
            $table->text('content');
            // Explicit: the first TIMESTAMP column would otherwise inherit
            // `ON UPDATE CURRENT_TIMESTAMP` in MySQL and the row would look
            // edited the next time anything touched it.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['medical_record_id', 'created_at']);
            $table->index(['clinical_evolution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_addenda');
    }
};
