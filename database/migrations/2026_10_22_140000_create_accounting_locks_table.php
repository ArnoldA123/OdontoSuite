<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F3 (#283, S4/S5) — the accounting freeze date per module.
     *
     * One row per (module, branch); a null `branch_id` is the group-wide row.
     * MySQL accepts repeated NULLs in a unique index, so "one global row per
     * module" is enforced by AccountingLockService::setLock() with
     * updateOrCreate, not by the schema. The index below serves the
     * (module, branch) resolution the service performs on every guard.
     */
    public function up(): void
    {
        Schema::create('accounting_locks', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('locked_through');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['module', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_locks');
    }
};
