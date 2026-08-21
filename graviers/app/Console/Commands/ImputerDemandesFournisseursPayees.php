<?php

namespace App\Console\Commands;

use App\Models\DemandePaiement;
use App\Models\Fournisseur;
use App\Models\PaiementFournisseur;
use Help;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impute sur les bons les demandes de paiement DÉJÀ acceptées.
 *
 * Une demande acceptée solde désormais les bons du fournisseur au moment de
 * la 2e validation. Mais ce mécanisme n'agit qu'à ce moment-là : les demandes
 * acceptées AVANT n'ont rien inscrit dans `paiement_fournisseur`.
 *
 * Leurs bons restent donc entièrement dus alors que le fournisseur a été payé.
 * Le popup « Enregistrer un paiement fournisseur » propose encore la totalité,
 * et un administrateur peut régler une seconde fois ce qui l'a déjà été.
 *
 * Cette commande répare ce qui a déjà été écrit. Elle est SANS EFFET sur le
 * solde du fournisseur : les règlements créés portent `demande_paiement_id` et
 * sont donc exclus du calcul, qui compte déjà la demande.
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan fournisseur:imputer-demandes-payees
 *   php artisan fournisseur:imputer-demandes-payees --apply
 */
class ImputerDemandesFournisseursPayees extends Command
{
    protected $signature = 'fournisseur:imputer-demandes-payees {--apply : Appliquer réellement (sinon simulation)}';
    protected $description = "Solde les bons pour les demandes de paiement fournisseurs déjà acceptées.";

    public function handle()
    {
        $apply = (bool) $this->option('apply');

        // La colonne vient de la migration `relier_les_paiements_aux_demandes`.
        // Sans elle, la commande — et surtout la 2e validation d'une demande —
        // s'arrêtent sur une erreur SQL brute. Autant le dire clairement.
        if (!Schema::hasColumn('paiement_fournisseur', 'demande_paiement_id')) {
            $this->error("La colonne `paiement_fournisseur.demande_paiement_id` n'existe pas.");
            $this->newLine();
            $this->line('La migration qui la crée n\'a pas encore été jouée. Lancez :');
            $this->newLine();
            $this->line('    php artisan migrate --force');
            $this->newLine();
            $this->warn("Tant qu'elle manque, la 2e validation d'une demande de paiement");
            $this->warn("fournisseur échoue aussi (la validation est annulée, rien n'est");
            $this->warn("écrit à moitié).");

            return self::FAILURE;
        }

        $demandes = DemandePaiement::query()
            ->join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', Help::$USER_FOURNISSEUR)
            ->whereNull('demande_paiement.deleted_at')
            // Acceptée, donc versée au fournisseur.
            ->where('demande_paiement.paye', 1)
            ->orderBy('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();

        $aImputer = [];

        foreach ($demandes as $demande) {
            // Déjà imputée : ne rien refaire. C'est ce qui rend la commande
            // rejouable sans risque de payer deux fois sur le papier.
            if (PaiementFournisseur::where('demande_paiement_id', $demande->id)->exists()) {
                continue;
            }

            $fournisseur = Fournisseur::where('user_id', $demande->user_id)->first();

            if (!$fournisseur) {
                continue;
            }

            $duRestant = (float) $fournisseur->enlevements()
                ->whereNotNull('fournisseur_validation')
                ->whereNull('deleted_at')
                ->get()
                ->sum(fn ($bon) => $bon->resteAPayer());

            $aImputer[] = [
                'demande'     => $demande,
                'fournisseur' => $fournisseur,
                'numero'      => $demande->numero ?: ('#' . $demande->id),
                'nom'         => $fournisseur->nom_prenoms ?: '-',
                'montant'     => (float) $demande->montant,
                'du_avant'    => $duRestant,
                // Ce qui pourra réellement être imputé : on n'invente pas de dette.
                'imputable'   => min((float) $demande->montant, $duRestant),
            ];
        }

        if (empty($aImputer)) {
            $this->info('Aucune demande à imputer : toutes les demandes acceptées ont déjà soldé leurs bons.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Demandes acceptées dont les bons sont restés dus :');
        $this->table(
            ['Demande', 'Fournisseur', 'Montant versé', 'Dû sur les bons', 'Sera imputé'],
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
                "%d demande(s) versent plus que ce que les bons doivent encore. Le reliquat",
                count($ecarts)
            ));
            $this->warn("ne sera imputé nulle part : la commande ne crée pas de dette qui");
            $this->warn("n'existe pas. Vérifiez ces lignes.");
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        $total = 0.0;

        DB::transaction(function () use ($aImputer, &$total) {
            foreach ($aImputer as $d) {
                $total += $d['fournisseur']->imputerDemandeSurLesBons($d['demande']);
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'Appliqué : %d demande(s) imputée(s), %s FCFA portés sur les bons.',
            count($aImputer),
            number_format($total, 0, ',', ' ')
        ));
        $this->line('Le popup « Enregistrer un paiement fournisseur » annonce désormais le dû réel.');

        return self::SUCCESS;
    }
}
