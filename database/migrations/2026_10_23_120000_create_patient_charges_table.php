<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #287 (T2, S3/S4) — the patient charge ledger.
 *
 * The balance of a patient used to be derived by comparing `transactions`
 * against `appointments.final_amount`, which mixed the drawer book with the
 * debt book. A charge is now a row of its own: the closure of the act writes
 * the one the act produced (`source = encounter`) and the counter has a door
 * for a charge no act produced (`source = manual`).
 *
 * The unique index on `clinical_encounter_id` is what makes "exactly one
 * charge per act" true at the schema level, not only in the service. The index
 * on `(patient_id, charged_at)` serves the future statement (#287, T3), which
 * reads a patient's ledger in chronological order.
 *
 * There is no `branch_id` on purpose: the charge follows the patient, whose
 * ficha is shared between sedes (ADR-0010).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('source', 16);
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            // Explicit DEFAULT: MySQL gives the first TIMESTAMP column
            // `ON UPDATE CURRENT_TIMESTAMP` when only `$table->timestamp()` is
            // declared, which would silently move `charged_at` on every update.
            $table->timestamp('charged_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'charged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_charges');
    }
};
