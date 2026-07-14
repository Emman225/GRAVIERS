<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AnnulerCommandesComptantPerimees extends Command
{
    protected $signature = 'gravier:annuler-comptant-perimes
        {--dry-run : Liste les commandes éligibles sans les annuler}';

    protected $description = "Annule automatiquement les commandes comptant non payées au-delà du délai d'annulation défini dans Configuration (delai_annulation_auto).";

    public function handle(): int
    {
        $config = Configuration::first();
        $delai  = (int) ($config?->delai_annulation_auto ?? 7);

        if ($delai <= 0) {
            $this->warn("delai_annulation_auto = {$delai}j → désactivé, aucune action.");
            return self::SUCCESS;
        }

        $dateLimite = Carbon::today()->subDays($delai);
        $clientsComptantIds = Client::where('client_a_terme', 0)->pluck('id');

        $candidates = Commande::whereIn('client_id', $clientsComptantIds)
            ->where('date_commande', '<', $dateLimite)
            ->where(function ($q) {
                $q->whereNull('statut_comptant')
                  ->orWhereNotIn('statut_comptant', ['Annulée', 'Payée', 'Livrée']);
            })
            ->get();

        $eligibles = $candidates->filter(fn(Commande $c) => $c->montantPayeComptant() <= 0);

        if ($eligibles->isEmpty()) {
            $this->info("Aucune commande comptant à annuler (délai = {$delai}j, date limite = {$dateLimite->format('d/m/Y')}).");
            return self::SUCCESS;
        }

        $this->info("Commandes éligibles à l'annulation auto : {$eligibles->count()}");
        foreach ($eligibles as $c) {
            $this->line(sprintf(
                "  - %s | client_id=%s | date_commande=%s | total=%s",
                $c->numero ?? "ID#{$c->id}",
                $c->client_id,
                $c->date_commande,
                $c->montant_total
            ));
        }

        if ($this->option('dry-run')) {
            $this->warn('Mode --dry-run : aucune mise à jour effectuée.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($eligibles as $c) {
            $c->statut_comptant = 'Annulée';
            $note = trim((string) $c->note);
            $tag  = "[Auto-annulée le " . Carbon::today()->format('d/m/Y') . " — délai {$delai}j dépassé sans paiement]";
            $c->note = $note ? ($note . "\n" . $tag) : $tag;
            $c->save();
            $count++;
        }

        $this->info("{$count} commande(s) annulée(s) automatiquement.");
        return self::SUCCESS;
    }
}
