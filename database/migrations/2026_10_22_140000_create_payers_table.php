<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convenios (#293 S3).
     *
     * A payer is the agreement a procedure can be priced against: the public
     * health insurer, a private one, a company. The code is the stable slug the
     * tariff rows and the future imports refer to; the name is what the operator
     * reads.
     */
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payers');
    }
};
