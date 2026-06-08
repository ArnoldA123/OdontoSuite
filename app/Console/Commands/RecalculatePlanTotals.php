<?php

namespace App\Console\Commands;

use App\Models\TreatmentPlan;
use App\Services\TreatmentPlanService;
use Illuminate\Console\Command;

class RecalculatePlanTotals extends Command
{
    protected $signature = 'plans:recalculate-totals
                            {--plan= : Recalcular solo este plan (ID)}
                            {--dry-run : Mostrar cambios sin aplicar}';

    protected $description = 'Recalcula los totales (total_cost, final_cost) de los planes de tratamiento';

    public function handle(TreatmentPlanService $service): int
    {
        $query = TreatmentPlan::query();

        if ($planId = $this->option('plan')) {
            $query->where('id', $planId);
        }

        $plans = $query->with('items')->get();

        if ($plans->isEmpty()) {
            $this->warn('No se encontraron planes para recalcular.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$plans->count()} plan(es)...");

        $bar = $this->output->createProgressBar($plans->count());
        $bar->start();

        $updated = 0;
        $unchanged = 0;
        $errors = 0;

        foreach ($plans as $plan) {
            try {
                $oldTotal = (float) $plan->total_cost;
                $oldFinal = (float) $plan->final_cost;

                $expectedSubtotal = $plan->items->sum(
                    fn ($i) => (float) $i->quantity * (float) $i->unit_cost
                );
                $expectedFinal = $expectedSubtotal - (float) ($plan->discount_amount ?? 0);

                $needsUpdate = round($oldTotal, 2) !== round($expectedSubtotal, 2)
                    || round($oldFinal, 2) !== round($expectedFinal, 2);

                if ($this->option('dry-run')) {
                    if ($needsUpdate) {
                        $this->newLine();
                        $this->line(sprintf(
                            '  Plan #%d (%s): total %.2f -> %.2f, final %.2f -> %.2f',
                            $plan->id,
                            $plan->plan_number,
                            $oldTotal,
                            $expectedSubtotal,
                            $oldFinal,
                            $expectedFinal
                        ));
                    }
                    $unchanged++;
                } else {
                    if ($needsUpdate) {
                        $service->calculateTotals($plan->id);
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("  Error en plan #{$plan->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$plans->count()} planes analizados, {$unchanged} ya correctos.");
        } else {
            $this->info("Listo. {$updated} actualizados, {$unchanged} ya correctos, {$errors} con error.");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
