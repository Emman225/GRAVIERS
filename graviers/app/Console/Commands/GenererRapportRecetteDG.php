<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenererRapportRecetteDG extends Command
{
    protected $signature = 'gravier:rapport-recette-dg
        {--out= : Chemin de sortie. Défaut : storage/app/public/rapport-recette-dg.pdf}';

    protected $description = "Génère un rapport PDF de recette destiné à la Direction Générale (résumé, livré, restes, plan de tests).";

    public function handle(): int
    {
        $out = $this->option('out') ?: storage_path('app/public/rapport-recette-dg.pdf');

        $dir = dirname($out);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $pdf = \PDF::loadView('admin.rapport.recette-dg')
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'defaultFont'          => 'DejaVu Sans',
            ]);

        file_put_contents($out, $pdf->output());

        $size = round(filesize($out) / 1024, 1);
        $this->info("Rapport généré : {$out} ({$size} Ko)");
        return self::SUCCESS;
    }
}
