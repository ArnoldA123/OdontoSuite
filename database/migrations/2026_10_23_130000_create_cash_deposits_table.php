<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #287 (T4, S5/S7) — the bank deposit.
 *
 * A deposit is money leaving the drawer towards the bank. The row keeps the
 * bank reference and the moment the money was deposited; the `cash_movement_id`
 * links the `withdrawal` the service writes in the same transaction, which is
 * what keeps `expected_amount` and the arqueo true (D5).
 *
 * The deposit belongs to a sede: the drawer, its movements and the
 * reconciliation are operational surfaces (ADR-0010), unlike the patient
 * ledger. The index on `(branch_id, deposited_at)` serves the filtered list and
 * the daily report; the one on `cash_register_session_id` serves the session
 * view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            // Explicit DEFAULT: MySQL gives the first TIMESTAMP column
            // `ON UPDATE CURRENT_TIMESTAMP` when only `$table->timestamp()` is
            // declared, which would silently move `deposited_at` on every
            // update.
            $table->timestamp('deposited_at')->useCurrent();
            $table->string('reference', 100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'deposited_at']);
            $table->index('cash_register_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_deposits');
    }
};
