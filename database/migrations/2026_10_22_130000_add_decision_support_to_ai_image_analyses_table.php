<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #296 — an AI analysis is decision support, not a diagnosis of its own.
 *
 * Four facts were missing from the row:
 *
 *  - the clinical act it supports. Until now it hung only from the attachment;
 *    F2 (#282) made the encounter the act and the attachment already belongs to
 *    it, so the analysis inherits that link and can appear in the act's record.
 *  - its annulment. A clinical support document is not erased: `voided_at`,
 *    `voided_by` and `void_reason` are the trace, and the review columns stay
 *    untouched so the professional's decision survives the annulment.
 *  - the patient notice. The professional declares that the patient was told
 *    the result is assistive; the row keeps who declared it and when.
 *  - what the service cost. `tokens_used` was recorded but never priced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_image_analyses', function (Blueprint $table) {
            $table->foreignId('clinical_encounter_id')
                ->nullable()
                ->after('clinical_attachment_id')
                ->constrained()
                ->nullOnDelete();

            $table->timestamp('voided_at')->nullable()->after('tokens_used');
            $table->foreignId('voided_by')
                ->nullable()
                ->after('voided_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('void_reason', 255)->nullable()->after('voided_by');

            $table->timestamp('notice_acknowledged_at')->nullable()->after('void_reason');
            $table->foreignId('notice_acknowledged_by')
                ->nullable()
                ->after('notice_acknowledged_at')
                ->constrained('users')
                ->nullOnDelete();

            // USD, four decimals: a single image costs a fraction of a cent.
            $table->decimal('estimated_cost', 10, 4)->nullable()->after('tokens_used');

            $table->index('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_image_analyses', function (Blueprint $table) {
            $table->dropIndex(['voided_at']);
            $table->dropConstrainedForeignId('notice_acknowledged_by');
            $table->dropColumn('notice_acknowledged_at');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason', 'estimated_cost']);
            $table->dropConstrainedForeignId('clinical_encounter_id');
        });
    }
};
