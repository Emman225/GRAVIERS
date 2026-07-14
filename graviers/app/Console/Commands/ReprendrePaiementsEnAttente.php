<?php

namespace App\Console\Commands;

use App\Http\Controllers\PaiementEnLigne;
use Illuminate\Console\Command;

class ReprendrePaiementsEnAttente extends Command
{
    /**
     * Reprend les paiements en ligne restés "en attente" et tente de les
     * régulariser auprès de la passerelle (filet si le callback s'est perdu).
     *
     * Exemple cron (toutes les 5 min) dans app/Console/Kernel.php :
     *   $schedule->command('paiements:reprendre-en-attente')->everyFiveMinutes();
     */
    protected $signature = 'paiements:reprendre-en-attente {--minutes=5 : Age minimum des paiements à reprendre} {--limit=100 : Nombre max de paiements traités}';

    protected $description = "Vérifie auprès de la passerelle les paiements en ligne en attente et les régularise s'ils sont payés";

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $limit   = (int) $this->option('limit');

        $controleur = new PaiementEnLigne();
        $res = $controleur->reprendrePaiementsEnAttente($minutes, $limit);

        $this->info("Paiements vérifiés : {$res['verifies']} — confirmés : {$res['confirmes']}");

        if (empty(config('paysecure.status_url'))) {
            $this->warn("PAYSECURE_STATUS_URL n'est pas configuré : la vérification automatique est inactive (seule la confirmation manuelle fonctionne).");
        }

        return self::SUCCESS;
    }
}
