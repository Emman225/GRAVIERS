<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Auto-annulation quotidienne des commandes comptant périmées (cf. configuration.delai_annulation_auto)
        $schedule->command('gravier:annuler-comptant-perimes')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->onOneServer();

        // Filet paiement en ligne : reprend les paiements restés en attente et les
        // régularise auprès de la passerelle (actif seulement si PAYSECURE_STATUS_URL est défini).
        $schedule->command('paiements:reprendre-en-attente')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
