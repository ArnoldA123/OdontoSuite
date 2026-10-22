<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * F2 (#282) — the clinical encounter is the act, the appointment is its
 * schedule projection.
 *
 * Until now the appointment carried everything that happened: the clinical
 * evolution, the findings, the materials, the attachments, the amount and the
 * quotation. That made a schedule row the owner of clinical documentation and
 * left no way to record an act that never had an appointment (urgency). The
 * encounter is that act: it has its own state (`open → closed | voided`), one
 * professional, one medical record, one plan and one amount, and an appointment
 * may have 0..n of them.
 *
 * The five tables the closure produces gain `clinical_encounter_id` and keep
 * `appointment_id`: existing readers (medical records, reports,
 * payment-preview) query by appointment, so the encounter becomes the owner
 * additively instead of breaking them. `procedure_materials.appointment_id` is
 * the only one that was NOT NULL, and an urgency encounter has no appointment,
 * so it becomes nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('medical_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 32)->nullable();
            $table->string('status', 16)->default('open');
            // Explicit DEFAULT: MySQL gives the first TIMESTAMP column
            // `ON UPDATE CURRENT_TIMESTAMP` when only `$table->timestamp()` is
            // declared, which would silently move `opened_at` on every update.
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->decimal('final_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'status']);
            $table->index(['patient_id', 'status']);
            $table->index(['status', 'opened_at']);
        });

        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->foreignId('clinical_encounter_id')
                ->nullable()
                ->after('appointment_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('odontogram_records', function (Blueprint $table) {
            $table->foreignId('clinical_encounter_id')
                ->nullable()
                ->after('appointment_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('clinical_attachments', function (Blueprint $table) {
            $table->foreignId('clinical_encounter_id')
                ->nullable()
                ->after('appointment_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('clinical_encounter_id')
                ->nullable()
                ->after('appointment_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->foreignId('clinical_encounter_id')
                ->nullable()
                ->after('appointment_id')
                ->constrained()
                ->nullOnDelete();
        });

        // The urgency encounter has no appointment. `procedure_materials` was
        // the only one of the five that declared appointment_id NOT NULL.
        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('procedure_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_encounter_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_encounter_id');
        });

        Schema::table('clinical_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_encounter_id');
        });

        Schema::table('odontogram_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_encounter_id');
        });

        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_encounter_id');
        });

        Schema::dropIfExists('clinical_encounters');
    }
};
