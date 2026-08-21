<?php

namespace App\Console\Commands;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\DemandePaiement;
use App\Models\PaiementApporteur;
use Help;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impute sur les commissions les demandes d'apporteur DÉJÀ acceptées.
 *
 * Une demande acceptée solde désormais les commissions de l'apporteur au
 * moment de la 2e validation. Mais ce mécanisme n'agit qu'à ce moment-là : les
 * demandes acceptées AVANT n'ont rien inscrit dans `paiement_apporteur`.
 *
 * Leurs commissions restent donc entièrement dues alors que l'apporteur a été
 * payé. Le formulaire « Enregistrer un paiement de commission » propose encore
 * la totalité, et un administrateur peut régler une seconde fois ce qui l'a
 * déjà été.
 *
 * Cette commande répare ce qui a déjà été écrit. Elle est SANS EFFET sur le
 * solde de l'apporteur : les règlements créés portent `demande_paiement_id` et
 * sont donc exclus du calcul, qui compte déjà la demande.
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan apporteur:imputer-demandes-payees
 *   php artisan apporteur:imputer-demandes-payees --apply
 */
class ImputerDemandesApporteursPayees extends Command
{
    protected $signature = 'apporteur:imputer-demandes-payees {--apply : Appliquer réellement (sinon simulation)}';
    protected $description = "Solde les commissions pour les demandes de paiement apporteurs déjà acceptées.";

    public function handle()
    {
        $apply = (bool) $this->option('apply');

        if (!Schema::hasColumn('paiement_apporteur', 'demande_paiement_id')) {
            $this->error("La colonne `paiement_apporteur.demande_paiement_id` n'existe pas.");
            $this->newLine();
            $this->line('La migration qui la crée n\'a pas encore été jouée. Lancez :');
            $this->newLine();
            $this->line('    php artisan migrate --force');

            return self::FAILURE;
        }

        $demandes = DemandePaiement::query()
            ->join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', Help::$USER_APPORTEUR)
            ->whereNull('demande_paiement.deleted_at')
            // Acceptée, donc versée à l'apporteur.
            ->where('demande_paiement.paye', 1)
            ->orderBy('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();

        $aImputer = [];

        foreach ($demandes as $demande) {
            // Déjà imputée : ne rien refaire. C'est ce qui rend la commande
            // rejouable sans risque de payer deux fois sur le papier.
            if (PaiementApporteur::where('demande_paiement_id', $demande->id)->exists()) {
                continue;
            }

            $apporteur = Apporteur::where('user_id', $demande->user_id)->first();

            if (!$apporteur) {
                continue;
            }

            $duRestant = (float) CommissionApporteur::where('apporteur_id', $apporteur->id)
                ->whereNull('deleted_at')
                ->get()
                ->sum(fn (CommissionApporteur $c) => $c->resteAPayerCommission());

            $aImputer[] = [
                'demande'   => $demande,
                'apporteur' => $apporteur,
                'numero'    => $demande->numero ?: ('#' . $demande->id),
                'nom'       => $apporteur->user?->nom_prenoms ?? '-',
                'montant'   => (float) $demande->montant,
                'du_avant'  => $duRestant,
                // Ce qui pourra réellement être imputé : on n'invente pas de dette.
                'imputable' => min((float) $demande->montant, $duRestant),
            ];
        }

        if (empty($aImputer)) {
            $this->info('Aucune demande à imputer : toutes les demandes acceptées ont déjà soldé leurs commissions.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Demandes acceptées dont les commissions sont restées dues :');
        $this->table(
            ['Demande', 'Apporteur', 'Montant versé', 'Dû sur les commissions', 'Sera imputé'],
            array_map(fn ($d) => [
                $d['numero'],
                $d['nom'],
                number_format($d['montant'], 0, ',', ' '),
                number_format($d['du_avant'], 0, ',', ' '),
                number_format($d['imputable'], 0, ',', ' '),
            ], $aImputer)
        );

        $ecarts = array_filter($aImputer, fn ($d) => $d['imputable'] < $d['montant']);

        if ($ecarts) {
            $this->newLine();
            $this->warn(sprintf(
                "%d demande(s) versent plus que ce que les commissions doivent encore.",
                count($ecarts)
            ));
            $this->warn("Le reliquat ne sera imputé nulle part : la commande ne crée pas une");
            $this->warn("dette qui n'existe pas. Vérifiez ces lignes.");
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        $total = 0.0;

        DB::transaction(function () use ($aImputer, &$total) {
            foreach ($aImputer as $d) {
                $total += $d['apporteur']->imputerDemandeSurLesCommissions($d['demande']);
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'Appliqué : %d demande(s) imputée(s), %s FCFA portés sur les commissions.',
            count($aImputer),
            number_format($total, 0, ',', ' ')
        ));
        $this->line('Le formulaire « Enregistrer un paiement de commission » annonce désormais le dû réel.');

        return self::SUCCESS;
    }
}
