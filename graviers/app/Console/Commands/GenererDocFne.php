<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Commande Artisan : produit le PDF de documentation FNE dans storage/app/public.
 *
 *   php artisan fne:doc
 */
class GenererDocFne extends Command
{
    protected $signature = 'fne:doc {--path= : Chemin de sortie du PDF}';
    protected $description = "Génère le PDF de documentation d'intégration FNE";

    public function handle(): int
    {
        $pdf = Pdf::loadView('document.fne_integration_doc')
            ->setPaper('a4', 'portrait');

        $path = $this->option('path')
            ?: storage_path('app/documentation-fne-graviers-' . now()->format('Ymd-His') . '.pdf');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $pdf->output());

        $this->info("Documentation FNE générée : " . $path);
        return self::SUCCESS;
    }
}
