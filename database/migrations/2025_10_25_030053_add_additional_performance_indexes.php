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
        // Verificar si los índices ya existen antes de crearlos
        // Índices para patients
        Schema::table('patients', function (Blueprint $table) {
            // Verificar si el índice no existe antes de crearlo
            if (!$this->indexExists('patients', 'patients_document_number_index')) {
                $table->index('document_number', 'patients_document_number_index');
            }
            
            if (!$this->indexExists('patients', 'patients_email_index')) {
                $table->index('email', 'patients_email_index');
            }
            
            if (!$this->indexExists('patients', 'patients_is_active_index')) {
                $table->index('is_active', 'patients_is_active_index');
            }
        });

        // Índices para transactions
        Schema::table('transactions', function (Blueprint $table) {
            if (!$this->indexExists('transactions', 'transactions_patient_id_created_at_index')) {
                $table->index(['patient_id', 'created_at'], 'transactions_patient_id_created_at_index');
            }
            
            if (!$this->indexExists('transactions', 'transactions_status_index')) {
                $table->index('status', 'transactions_status_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_document_number_index');
            $table->dropIndex('patients_email_index');
            $table->dropIndex('patients_is_active_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_patient_id_created_at_index');
            $table->dropIndex('transactions_status_index');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $table, $indexName]
        );
        
        return $result[0]->count > 0;
    }
};

