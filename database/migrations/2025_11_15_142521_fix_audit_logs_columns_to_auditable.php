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
        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop old indexes
            $table->dropIndex(['entity_type', 'entity_id']);
            
            // Rename columns
            $table->renameColumn('entity_type', 'auditable_type');
            $table->renameColumn('entity_id', 'auditable_id');
            
            // Add new index with correct column names
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop new index
            $table->dropIndex(['auditable_type', 'auditable_id']);
            
            // Rename columns back
            $table->renameColumn('auditable_type', 'entity_type');
            $table->renameColumn('auditable_id', 'entity_id');
            
            // Add old index
            $table->index(['entity_type', 'entity_id']);
        });
    }
};
