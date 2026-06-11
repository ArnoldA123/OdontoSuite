<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 (M-1): agregar SoftDeletes a ClinicalAttachment.
 * Tabla: clinical_attachments
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_attachments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_attachments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
