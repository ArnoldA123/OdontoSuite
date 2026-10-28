<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #427 — a failed analysis explains itself to the professional.
 *
 * The service catches the provider error and keeps the technical message in
 * `raw_response`, which is audit material and never leaves the API. That left
 * the reader with `failed` and no cause. This column is the readable reason the
 * detail paints: a short sentence, not the provider body.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_image_analyses', function (Blueprint $table) {
            $table->text('failure_reason')->nullable()->after('raw_response');
        });
    }

    public function down(): void
    {
        Schema::table('ai_image_analyses', function (Blueprint $table) {
            $table->dropColumn('failure_reason');
        });
    }
};
