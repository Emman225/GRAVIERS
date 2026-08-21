<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

/**
 * État du crédit des clients à terme, AVANT d'activer le contrôle du plafond
 * sur les demandes de livraison.
 *
 * Le contrôle refuse une demande dont le montant dépasse le crédit disponible.
 * Il s'applique dès le déploiement, aux clients existants comme aux nouveaux :
 * un client déjà au-delà de son plafond verra sa prochaine demande refusée, là
 * où elle passait sans rien dire.
 *
 * Cette commande donne le chiffre EXACT que le contrôle utilisera, puisqu'elle
 * appelle les mêmes méthodes du modèle. Une requête SQL écrite à la main ne le
 * donnerait pas : le reste dû se calcule depuis les lignes, la TVA, la
 * livraison et les encaissements validés.
 *
 * Lecture seule : elle ne modifie rien.
 *
 *   php artisan client:encours-terme
 */
class EncoursClientsATerme extends Command
{
    protected $signature = 'client:encours-terme';
    protected $description = "Affiche le plafond, l'encours et le crédit disponible de chaque client à terme.";

    public function handle()
    {
        $clients = Client::where('client_a_terme', 1)->orderBy('nom')->get();

        if ($clients->isEmpty()) {
            $this->info('Aucun client à terme.');
            return self::SUCCESS;
        }

        $lignes    = [];
        $depassent = 0;
        $sansPlafond = 0;

        foreach ($clients as $client) {
            $encours    = $client->encoursCredit();
            $plafond    = $client->plafond_credit;
            $disponible = $client->plafondDisponible();

            // plafondDisponible() renvoie null dès que le plafond vaut zéro —
            // la colonne est NOT NULL et vaut 0 par défaut, ce qui signifie
            // « aucun plafond accordé ». Le contrôle laisse alors passer : il ne
            // fait pas respecter une limite qui n'existe pas.
            if ($disponible === null) {
                $sansPlafond++;
                $etat = 'aucun plafond — jamais bloqué';
            } elseif ($disponible <= 0) {
                $depassent++;
                $etat = 'PLAFOND ATTEINT — toute nouvelle demande sera refusée';
            } else {
                $etat = 'ok';
            }

            $lignes[] = [
                $client->id,
                $client->display_name,
                (float) $plafond > 0 ? number_format((float) $plafond, 0, ',', ' ') : '—',
                number_format($encours, 0, ',', ' '),
                $disponible === null ? '—' : number_format($disponible, 0, ',', ' '),
                $etat,
            ];
        }

        $this->newLine();
        $this->table(
            ['ID', 'Client', 'Plafond', 'Encours', 'Disponible', 'État'],
            $lignes
        );

        $this->newLine();
        $this->line("L'encours additionne le reste dû sur les COMMANDES, les LOCATIONS et les DEMANDES DE LIVRAISON.");

        if ($depassent > 0) {
            $this->warn("{$depassent} client(s) ont déjà atteint leur plafond : leur prochaine commande, location ou demande de livraison sera refusée.");
            $this->line("Pour les débloquer : encaisser ce qui est dû, ou relever leur plafond dans leur fiche client.");
        } else {
            $this->info('Aucun client à terme au-delà de son plafond.');
        }

        if ($sansPlafond > 0) {
            $this->line("{$sansPlafond} client(s) à terme sans plafond accordé : le contrôle ne les bloquera jamais.");
        }

        return self::SUCCESS;
    }
}
