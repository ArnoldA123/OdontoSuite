<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * #291, S3/S4 — every payment method now declares what it is and what it needs.
 *
 * `type` is the behaviour family the daily reconciliation groups by (cash, card,
 * transfer, wallet); `requires_reference` is the per-method rule that refuses a
 * charge without an operation number or voucher.
 *
 * Additive only: the columns arrive with a default, and the rows that already
 * exist are backfilled by `code` with the Query Builder (migrations never touch
 * Eloquent).
 */
return new class extends Migration
{
    /**
     * Family of each seeded method, keyed by the code it was created with.
     *
     * @var array<string, string[]>
     */
    private const FAMILIES = [
        'cash' => ['cash'],
        'transfer' => ['transfer'],
        'card' => ['credit_card', 'debit_card'],
        'wallet' => ['yape', 'plin'],
    ];

    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('type', 20)->default('cash')->after('code');
            $table->boolean('requires_reference')->default(false)->after('requires_authorization');
        });

        foreach (self::FAMILIES as $type => $codes) {
            DB::table('payment_methods')
                ->whereIn('code', $codes)
                ->update(['type' => $type]);
        }

        // Money that arrives through a bank, a POS or a wallet leaves an
        // operation number behind; physical cash does not.
        DB::table('payment_methods')
            ->whereIn('type', ['card', 'transfer', 'wallet'])
            ->update(['requires_reference' => true]);
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['type', 'requires_reference']);
        });
    }
};
