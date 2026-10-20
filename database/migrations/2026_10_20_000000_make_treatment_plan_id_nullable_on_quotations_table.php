<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Manual quotations have no treatment plan, but the original column was
 * created as a required foreign key. The request contract
 * (StoreQuotationRequest) and the UI already treat the plan as optional, so
 * the schema regains that contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('treatment_plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('treatment_plan_id')->nullable(false)->change();
        });
    }
};
