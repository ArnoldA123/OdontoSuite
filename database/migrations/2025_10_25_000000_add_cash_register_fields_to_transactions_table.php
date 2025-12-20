<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add cash register session relationship
            $table->foreignId('cash_register_session_id')->nullable()->constrained()->onDelete('set null')->after('created_by');

            // Add discount fields
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->after('amount');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_type');
            $table->foreignId('discount_authorized_by')->nullable()->constrained('users')->onDelete('set null')->after('discount_amount');

            // Add subtotal and tax fields
            $table->decimal('subtotal', 10, 2)->nullable()->after('discount_amount');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('subtotal');

            // Update type enum to include income/expense
            $table->dropColumn('type');
        });

        // Re-add type column with new values
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', ['income', 'expense', 'payment', 'refund', 'discount', 'adjustment'])->default('income')->after('transaction_number');
        });

        // Add indexes for new fields
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['cash_register_session_id', 'status']);
            $table->index(['discount_authorized_by']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['cash_register_session_id', 'status']);
            $table->dropIndex(['discount_authorized_by']);
            $table->dropIndex(['type', 'status']);

            // Drop new columns
            $table->dropForeign(['cash_register_session_id']);
            $table->dropColumn('cash_register_session_id');
            $table->dropColumn('discount_type');
            $table->dropColumn('discount_amount');
            $table->dropForeign(['discount_authorized_by']);
            $table->dropColumn('discount_authorized_by');
            $table->dropColumn('subtotal');
            $table->dropColumn('tax_amount');
        });
    }
};

