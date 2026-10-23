<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * F6 (#286) — the encounter becomes the compliance record of NTS 139.
 *
 * `reason_for_consultation` is the patient's complaint captured on the act, and
 * the four signature columns are the professional's signature at the close:
 * who signed, when, with which licence and over which frozen content
 * (`signature_hash`). Every column is nullable because the historical acts of
 * #282 predate the rule and are never rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_encounters', function (Blueprint $table) {
            $table->text('reason_for_consultation')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_license', 50)->nullable();
            $table->string('signature_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_encounters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_by');
            $table->dropColumn([
                'reason_for_consultation',
                'signed_at',
                'signer_license',
                'signature_hash',
            ]);
        });
    }
};
