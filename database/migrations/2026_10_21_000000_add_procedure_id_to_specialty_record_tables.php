<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Issue #247: the store contract validated `procedure_id` since slice 02
 * (T-02.10, BF-007), but none of the five specialty tables had the column, so
 * `validated()` dropped the link silently.
 *
 * One nullable FK per table follows the existing `appointments.procedure_id`
 * and `quotations.procedure_id` pattern: a clinical record executes one
 * catalog procedure. `nullOnDelete` keeps the clinical history when a catalog
 * entry retires.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'implantology_records',
        'orthodontics_records',
        'endodontics_records',
        'rehabilitation_records',
        'oral_surgery_records',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('procedure_id')
                    ->nullable()
                    ->after('appointment_id')
                    ->constrained('procedure_catalog')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['procedure_id']);
                $table->dropColumn('procedure_id');
            });
        }
    }
};
