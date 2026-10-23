<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * #287, T1 — the money vocabulary leaves the drawer alone.
 *
 * `write_off` (condonación) joins the enum so a forgiven debt is its own row
 * instead of an expense; `payment_method_id` becomes nullable because a
 * ledger entry has no method to be charged with; and the row gains the
 * authorization trail (`authorized_by` + `authorization_reason`) plus the
 * payment a refund gives money back from (`parent_transaction_id`).
 *
 * The enum cannot be altered through the schema builder, so it is a raw ALTER
 * guarded by driver, exactly like `2026_09_19_000001_add_voided_to_transactions_status`.
 * SQLite never builds this schema (decision #79) and cannot drop foreign keys,
 * so the migration stays out of its way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `transactions` MODIFY `type` ENUM('income','expense','payment','refund','discount','adjustment','write_off') NOT NULL DEFAULT 'payment'"
            );
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_method_id')->nullable()->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('payment_method_id')
                ->references('id')
                ->on('payment_methods')
                ->cascadeOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('authorized_by')
                ->nullable()
                ->after('discount_authorized_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('authorization_reason', 255)->nullable()->after('authorized_by');
            $table->foreignId('parent_transaction_id')
                ->nullable()
                ->after('authorization_reason')
                ->constrained('transactions')
                ->nullOnDelete();
        });
    }

    /**
     * Best effort: the new columns are removed and the method is required
     * again, but a ledger that already holds a method-less row or a `write_off`
     * cannot be squeezed back into the old shape without losing money, so the
     * enum revert is attempted only when no such row exists.
     */
    public function down(): void
    {
        if (! Schema::hasTable('transactions') || DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('transactions', 'parent_transaction_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['parent_transaction_id']);
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('parent_transaction_id');
            });
        }

        if (Schema::hasColumn('transactions', 'authorized_by')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['authorized_by']);
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn(['authorized_by', 'authorization_reason']);
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $legacyRows = DB::table('transactions')
                ->whereNull('payment_method_id')
                ->orWhere('type', 'write_off')
                ->exists();

            if (! $legacyRows) {
                DB::statement(
                    "ALTER TABLE `transactions` MODIFY `type` ENUM('income','expense','payment','refund','discount','adjustment') NOT NULL DEFAULT 'income'"
                );

                Schema::table('transactions', function (Blueprint $table) {
                    $table->dropForeign(['payment_method_id']);
                });

                Schema::table('transactions', function (Blueprint $table) {
                    $table->unsignedBigInteger('payment_method_id')->nullable(false)->change();
                });

                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreign('payment_method_id')
                        ->references('id')
                        ->on('payment_methods')
                        ->cascadeOnDelete();
                });
            }
        }
    }
};
