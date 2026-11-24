<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Slice 1 of «Registros de especialidad integrados a la atención y la historia».
 *
 * The five specialty tables hold structured clinical data the SOAP evolution
 * does not capture, but they lived isolated: a record did not say which act
 * produced it nor which line of the accepted plan it executed. Two nullable
 * links close that gap without touching a single existing row.
 *
 * `nullOnDelete` on both sides follows the `procedure_id` precedent of #247:
 * retiring a plan line or voiding an act must never erase the clinical record
 * that named it, it only stops pointing at it.
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
                $table->foreignId('clinical_encounter_id')
                    ->nullable()
                    ->after('appointment_id')
                    ->index()
                    ->constrained('clinical_encounters')
                    ->nullOnDelete();

                $table->foreignId('treatment_plan_item_id')
                    ->nullable()
                    ->after('clinical_encounter_id')
                    ->index()
                    ->constrained('treatment_plan_items')
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
                $table->dropForeign(['clinical_encounter_id']);
                $table->dropForeign(['treatment_plan_item_id']);
                $table->dropColumn(['clinical_encounter_id', 'treatment_plan_item_id']);
            });
        }
    }
};
