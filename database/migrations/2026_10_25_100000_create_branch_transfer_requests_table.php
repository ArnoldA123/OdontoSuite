<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * F3 follow-up (#305) — the branch transfer request.
 *
 * #283/#290 fixed the affiliation rule (ADR-0010): `users.branch_id` is the
 * sede of the user and `null` is group scope. Moving a professional between
 * sedes was an administrative edit with no request, no state and no reason.
 * This row is that process: the affiliation it copies when the user asks
 * (`from_branch_id`), the sede asked for (`to_branch_id`), and the decision
 * with its actor and notes.
 *
 * Both branches are `restrictOnDelete`: the history must keep naming the sede
 * it mentions, so a sede with transfer history is retired (deactivated), not
 * deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            // The three reads of S5: my requests, the ones entering a sede and
            // the ones leaving it.
            $table->index(['user_id', 'status']);
            $table->index(['to_branch_id', 'status']);
            $table->index(['from_branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_transfer_requests');
    }
};
