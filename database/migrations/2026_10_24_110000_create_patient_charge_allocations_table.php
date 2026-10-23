<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #321 (S1-S3) — which charge a payment paid.
 *
 * #287 left the patient balance aggregated: a payment reduced the debt of the
 * patient and nothing could answer which charge it settled. The allocation is a
 * view over the same money, not a second accounting: the payment stays in
 * `transactions`, the charge stays in `patient_charges`, and this row only says
 * how much of one covered the other.
 *
 * One row per (charge, payment) pair, so a payment covers several charges with
 * several rows and a charge receives several payments with several rows. The
 * sum rules —a payment never allocates more than its amount and a charge never
 * receives more than its own amount— live in `PatientChargeAllocationService`.
 *
 * No `branch_id` on purpose: the ledger follows the patient, whose ficha is
 * shared between sedes (ADR-0010).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_charge_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // MySQL caps identifier names at 64 characters: the generated name
            // of this pair would be one character short of 70.
            $table->unique(['patient_charge_id', 'transaction_id'], 'patient_charge_allocation_unique');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_charge_allocations');
    }
};
