<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Demo cleanup: one account per role, no "Test" duplicates.
 *
 * Deleting a user is not a safe operation in this schema: `appointments`,
 * `transactions`, `cash_movements`, `medical_records` and `clinical_evolutions`
 * declare CASCADE over `users`, so a bare delete would take the whole clinical
 * demo down with it. This command measures every user reference first, moves
 * the rows that must survive to the retained account of the same role, deletes
 * only the account's personal artifacts, and removes the user inside one
 * transaction. Nothing is written in --dry-run, a second run is a no-op, and a
 * foreign key that the map does not know aborts the run with the table, the
 * column and the affected rows named.
 */
class PruneDemoTestUsers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'demo:prune-test-users
        {--dry-run : Reporta el plan y no escribe nada}';

    /**
     * @var string
     */
    protected $description = 'Reasigna dependencias y elimina las cuentas demo de prueba, dejando nueve cuentas depuradas';

    /** The only account that keeps group scope (ADR-0010). */
    public const GROUP_SCOPE_EMAIL = 'admin+administrador@test.com';

    /**
     * Demo account to remove => retained account that inherits its rows.
     *
     * @var array<string, string>
     */
    public const REASSIGNMENTS = [
        'ever+administrador@test.com' => 'elizabet+administrador@test.com',
        'odontologo+odontologo@test.com' => 'ever+odontologo@test.com',
        'implantologo+implantologo@test.com' => 'wilmer+implantologo@test.com',
        'tecnico+tecnico@test.com' => 'sofia+tecnico@test.com',
        'asistente+asistente@test.com' => 'azul+asistente@test.com',
        'finanzas+finanzas@test.com' => 'milagros+finanzas@test.com',
    ];

    /**
     * Retained accounts whose display name the cleanup normalizes.
     *
     * @var array<string, string>
     */
    public const RENAMES = [
        self::GROUP_SCOPE_EMAIL => 'Gerencia OdontoSuite',
        'recepcionista+recepcionista@test.com' => 'Rosa Quispe Flores',
    ];

    /**
     * Every FK that references `users` and whose rows must survive a prune.
     *
     * It mirrors the schema: a new table that references `users` is caught by
     * the preflight scan and aborts the run until its column is added here.
     *
     * @var array<string, list<string>>
     */
    private const BUSINESS_REFERENCES = [
        'accounting_locks' => ['set_by'],
        'ai_analysis_citations' => ['cited_by'],
        'ai_image_analyses' => ['notice_acknowledged_by', 'requested_by', 'reviewed_by', 'voided_by'],
        'appointment_blocks' => ['created_by', 'user_id'],
        'appointment_custody_logs' => ['actor_id'],
        'appointments' => ['announced_by', 'checked_in_by', 'created_by', 'updated_by', 'user_id'],
        'audit_logs' => ['user_id'],
        'branch_transfer_requests' => ['decided_by', 'user_id'],
        'cash_deposits' => ['created_by'],
        'cash_movements' => ['created_by'],
        'cash_register_sessions' => ['user_id'],
        'clinical_addenda' => ['user_id'],
        'clinical_attachments' => ['created_by'],
        'clinical_encounters' => ['closed_by', 'signed_by', 'user_id', 'voided_by'],
        'clinical_evolutions' => ['created_by'],
        'continuity_requests' => ['created_by'],
        'endodontics_records' => ['created_by'],
        'implantology_records' => ['created_by'],
        'interconsultations' => ['from_specialist_id', 'to_specialist_id'],
        'lab_cases' => ['created_by', 'professional_id', 'technician_id'],
        'medical_records' => ['created_by'],
        'odontogram_records' => ['created_by'],
        'odontograms' => ['created_by'],
        'oral_surgery_records' => ['created_by'],
        'orthodontics_records' => ['created_by'],
        'patient_charge_allocations' => ['created_by'],
        'patient_charges' => ['created_by'],
        'patient_recalls' => ['created_by'],
        'payment_plans' => ['created_by'],
        'procedure_catalog_versions' => ['changed_by'],
        'procedure_materials' => ['created_by'],
        'purchase_orders' => ['created_by'],
        'quotations' => ['created_by'],
        'receipts' => ['created_by'],
        'rehabilitation_records' => ['created_by'],
        'stock_movements' => ['created_by'],
        'transactions' => ['authorized_by', 'created_by', 'discount_authorized_by'],
        'treatment_plan_item_alternatives' => ['created_by'],
        'treatment_plan_item_consumptions' => ['consumed_by'],
        'treatment_plan_versions' => ['accepted_by', 'created_by'],
        'treatment_plans' => ['created_by'],
        'waiting_lists' => ['created_by', 'preferred_user_id'],
        'work_schedules' => ['user_id'],
    ];

    /**
     * Rows that belong to the account itself and die with it.
     *
     * @var array<string, string>
     */
    private const PERSONAL_REFERENCES = [
        'user_specialties' => 'user_id',
        'user_favorite_procedures' => 'user_id',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (in_array(self::GROUP_SCOPE_EMAIL, array_keys(self::REASSIGNMENTS), true)) {
            $this->error('El administrador de grupo no puede formar parte de las cuentas a depurar.');

            return self::FAILURE;
        }

        $missing = $this->missingTargets();
        if ($missing !== []) {
            $this->error('No se puede continuar: faltan las cuentas conservadas que reciben las filas.');
            foreach ($missing as $email => $reason) {
                $this->line("  {$email}: {$reason}");
            }

            return self::FAILURE;
        }

        $missingRole = [];
        $sources = $this->sources($missingRole);
        if ($missingRole !== []) {
            $this->error('No se puede continuar: la cuenta conservada no tiene el mismo rol.');
            foreach ($missingRole as $line) {
                $this->line("  {$line}");
            }

            return self::FAILURE;
        }

        $this->line('Cuentas a depurar: '.count($sources));
        foreach ($sources as $email => $pair) {
            $this->line(sprintf(
                '  %s => %s (%s)',
                $email,
                $pair['target']->email,
                $pair['source']->role
            ));
        }

        $ids = array_map(static fn (array $pair): int => (int) $pair['source']->id, $sources);

        $uncovered = $this->uncoveredUserReferences($ids);
        if ($uncovered !== []) {
            $this->error('No se puede continuar: hay referencias a users que el comando no sabe reasignar.');
            foreach ($uncovered as $reference) {
                $this->line(sprintf(
                    '  Tabla «%s», columna «%s» (FK «%s»): %d fila(s) de las cuentas a eliminar (ids: %s).',
                    $reference['table'],
                    $reference['column'],
                    $reference['constraint'],
                    $reference['rows'],
                    $reference['ids']
                ));
            }
            $this->warn('No se escribió nada.');

            return self::FAILURE;
        }

        $collisions = $this->appointmentSlotCollisions($sources);
        if ($collisions !== []) {
            $this->error('No se puede continuar: reasignar las citas choca con la agenda de la cuenta conservada.');
            foreach ($collisions as $collision) {
                $this->line(sprintf(
                    '  La cita %d de %s choca con la cita %d de %s en %s.',
                    $collision['source_appointment'],
                    $collision['source_email'],
                    $collision['target_appointment'],
                    $collision['target_email'],
                    $collision['scheduled_at']
                ));
            }
            $this->warn('No se escribió nada.');

            return self::FAILURE;
        }

        $reassignments = $this->measureReassignments($sources);
        $personal = $this->measurePersonalArtifacts($sources);
        $renames = $this->pendingRenames();

        $totalReassignments = array_sum($reassignments);
        $totalPersonal = array_sum($personal);

        $verb = $dryRun ? 'a reasignar' : 'reasignadas';
        $this->line("Dependencias {$verb}: {$totalReassignments}");
        foreach ($reassignments as $reference => $rows) {
            if ($rows > 0) {
                $this->line("  {$reference}: {$rows}");
            }
        }

        $personalVerb = $dryRun ? 'a eliminar' : 'eliminados';
        $this->line("Registros personales {$personalVerb}: {$totalPersonal}");
        foreach ($personal as $reference => $rows) {
            if ($rows > 0) {
                $this->line("  {$reference}: {$rows}");
            }
        }

        $renameVerb = $dryRun ? 'a aplicar' : 'aplicados';
        $this->line("Renombres {$renameVerb}: ".count($renames));
        foreach ($renames as $email => $name) {
            $this->line("  {$email} => {$name}");
        }

        if ($dryRun) {
            $this->line('Cuentas a eliminar: '.count($sources));
            $this->warn('dry-run: no se escribió nada.');

            return self::SUCCESS;
        }

        try {
            $deleted = DB::transaction(function () use ($sources, $renames, $ids): int {
                foreach ($sources as $pair) {
                    foreach (self::BUSINESS_REFERENCES as $table => $columns) {
                        foreach ($columns as $column) {
                            DB::table($table)
                                ->where($column, $pair['source']->id)
                                ->update([$column => $pair['target']->id]);
                        }
                    }
                }

                foreach ($renames as $email => $name) {
                    DB::table('users')
                        ->where('email', $email)
                        ->where('name', '!=', $name)
                        ->update(['name' => $name, 'updated_at' => now()]);
                }

                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $ids)
                    ->delete();

                DB::table('password_reset_tokens')
                    ->whereIn('email', array_keys($sources))
                    ->delete();

                foreach (self::PERSONAL_REFERENCES as $table => $column) {
                    DB::table($table)->whereIn($column, $ids)->delete();
                }

                return DB::table('users')->whereIn('id', $ids)->delete();
            });
        } catch (QueryException $exception) {
            return $this->abortOnForeignKeys($exception, $ids);
        }

        $this->line("Cuentas eliminadas: {$deleted}");
        $this->line('Usuarios restantes: '.User::query()->count());

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>                            $missing
     * @return array<string, array{source: User, target: User}>
     */
    private function sources(array &$missingRole = []): array
    {
        $sources = [];

        foreach (self::REASSIGNMENTS as $sourceEmail => $targetEmail) {
            $source = User::query()->where('email', $sourceEmail)->first();
            if ($source === null) {
                continue;
            }

            $target = User::query()->where('email', $targetEmail)->first();
            if ($target === null || $target->id === $source->id) {
                continue;
            }

            if ($source->role !== $target->role) {
                $missingRole[] = "{$sourceEmail} ({$source->role}) => {$targetEmail} ({$target->role})";

                continue;
            }

            $sources[$sourceEmail] = ['source' => $source, 'target' => $target];
        }

        return $sources;
    }

    /**
     * @return array<string, string> email => reason
     */
    private function missingTargets(): array
    {
        $missing = [];

        foreach (self::REASSIGNMENTS as $sourceEmail => $targetEmail) {
            if (User::query()->where('email', $sourceEmail)->doesntExist()) {
                continue;
            }

            if (User::query()->where('email', $targetEmail)->doesntExist()) {
                $missing[$targetEmail] = "la cuenta conservada de {$sourceEmail} no existe";
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, array{source: User, target: User}> $sources
     * @return array<string, int>
     */
    private function measureReassignments(array $sources): array
    {
        $measured = [];

        foreach (self::BUSINESS_REFERENCES as $table => $columns) {
            foreach ($columns as $column) {
                $rows = 0;
                foreach ($sources as $pair) {
                    $rows += DB::table($table)->where($column, $pair['source']->id)->count();
                }
                $measured["{$table}.{$column}"] = $rows;
            }
        }

        return $measured;
    }

    /**
     * @param  array<string, array{source: User, target: User}> $sources
     * @return array<string, int>
     */
    private function measurePersonalArtifacts(array $sources): array
    {
        $ids = array_map(static fn (array $pair): int => (int) $pair['source']->id, $sources);
        $emails = array_keys($sources);

        $measured = [
            'personal_access_tokens' => DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $ids)
                ->count(),
            'password_reset_tokens' => DB::table('password_reset_tokens')
                ->whereIn('email', $emails)
                ->count(),
        ];

        foreach (self::PERSONAL_REFERENCES as $table => $column) {
            $measured[$table] = DB::table($table)->whereIn($column, $ids)->count();
        }

        return $measured;
    }

    /** @return array<string, string> email => normalized name */
    private function pendingRenames(): array
    {
        $pending = [];

        foreach (self::RENAMES as $email => $name) {
            $current = User::query()->where('email', $email)->value('name');
            if ($current !== null && $current !== $name) {
                $pending[$email] = $name;
            }
        }

        return $pending;
    }

    /**
     * A new table that references `users` is not silently ignored: the command
     * cannot know what to do with those rows, so it reports them and stops.
     *
     * @param  list<int>                                                                              $ids
     * @return list<array{table: string, column: string, constraint: string, rows: int, ids: string}>
     */
    private function uncoveredUserReferences(array $ids): array
    {
        if ($ids === [] || DB::connection()->getDriverName() !== 'mysql') {
            return [];
        }

        $known = [];
        foreach (self::BUSINESS_REFERENCES as $table => $columns) {
            foreach ($columns as $column) {
                $known["{$table}.{$column}"] = true;
            }
        }
        foreach (self::PERSONAL_REFERENCES as $table => $column) {
            $known["{$table}.{$column}"] = true;
        }

        $foreignKeys = DB::select(
            'select k.table_name as table_name, k.column_name as column_name, k.constraint_name as constraint_name
             from information_schema.key_column_usage k
             where k.table_schema = ? and k.referenced_table_name = ?',
            [DB::getDatabaseName(), 'users']
        );

        $uncovered = [];
        foreach ($foreignKeys as $foreignKey) {
            $reference = "{$foreignKey->table_name}.{$foreignKey->column_name}";
            if (isset($known[$reference])) {
                continue;
            }

            $rows = DB::table($foreignKey->table_name)
                ->whereIn($foreignKey->column_name, $ids)
                ->count();

            if ($rows === 0) {
                continue;
            }

            $sample = DB::table($foreignKey->table_name)
                ->whereIn($foreignKey->column_name, $ids)
                ->limit(5)
                ->pluck('id')
                ->implode(', ');

            $uncovered[] = [
                'table' => $foreignKey->table_name,
                'column' => $foreignKey->column_name,
                'constraint' => $foreignKey->constraint_name,
                'rows' => $rows,
                'ids' => $sample,
            ];
        }

        return $uncovered;
    }

    /**
     * @param  array<string, array{source: User, target: User}>                                                                                $sources
     * @return list<array{source_appointment: int, source_email: string, target_appointment: int, target_email: string, scheduled_at: string}>
     */
    private function appointmentSlotCollisions(array $sources): array
    {
        $collisions = [];

        foreach ($sources as $email => $pair) {
            $appointments = DB::table('appointments')
                ->where('user_id', $pair['source']->id)
                ->get(['id', 'scheduled_at', 'ends_at', 'slot_reservation']);

            foreach ($appointments as $appointment) {
                $query = DB::table('appointments')
                    ->where('user_id', $pair['target']->id)
                    ->where('scheduled_at', $appointment->scheduled_at)
                    ->where('ends_at', $appointment->ends_at);

                if ($appointment->slot_reservation === null) {
                    $query->whereNull('slot_reservation');
                } else {
                    $query->where('slot_reservation', $appointment->slot_reservation);
                }

                $conflict = $query->first(['id']);
                if ($conflict === null) {
                    continue;
                }

                $collisions[] = [
                    'source_appointment' => (int) $appointment->id,
                    'source_email' => $email,
                    'target_appointment' => (int) $conflict->id,
                    'target_email' => $pair['target']->email,
                    'scheduled_at' => (string) $appointment->scheduled_at,
                ];
            }
        }

        return $collisions;
    }

    /**
     * @param list<int> $ids
     */
    private function abortOnForeignKeys(QueryException $exception, array $ids): int
    {
        $this->error('No se pudo eliminar las cuentas demo: una restricción de clave foránea lo impide.');
        $this->describeForeignKeys($exception->getMessage(), $ids);
        $this->warn('No se escribió nada: la transacción se revirtió.');

        return self::FAILURE;
    }

    /**
     * @param list<int> $ids
     */
    private function describeForeignKeys(string $message, array $ids): void
    {
        $pattern = '/constraint fails \(`[^`]+`\.`([^`]+)`, CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\)/';

        if (preg_match($pattern, $message, $matches) !== 1) {
            $this->line('  '.$message);

            return;
        }

        [, $table, $constraint, $column] = $matches;

        $rows = DB::table($table)->whereIn($column, $ids)->count();
        $sample = DB::table($table)
            ->whereIn($column, $ids)
            ->limit(5)
            ->pluck('id')
            ->implode(', ');

        $this->line(sprintf(
            '  Tabla «%s», columna «%s» (FK «%s»): %d fila(s) de las cuentas a eliminar (ids: %s).',
            $table,
            $column,
            $constraint,
            $rows,
            $sample
        ));
    }
}
