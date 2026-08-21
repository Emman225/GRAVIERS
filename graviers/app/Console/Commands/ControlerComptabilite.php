<?php

namespace App\Console\Commands;

use App\Http\Controllers\ComptabiliteController;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Enlevement;
use App\Models\Livraison;
use App\Models\TvaCommande;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Contrôle des deux états comptables sur les données réelles.
 *
 * Les écrans « État de TVA collectée » et « Bénéfices sur les livraisons »
 * agrègent : un agrégat juste peut masquer des lignes fausses qui se
 * compensent. Cette commande recoupe leurs totaux avec les tables d'origine et
 * signale ce qui ne se réconcilie pas.
 *
 * Elle ne corrige RIEN : une écriture comptable ne se réécrit pas en silence.
 * Elle dit où regarder.
 *
 * Lecture seule.
 *
 *   php artisan compta:controler
 *   php artisan compta:controler --du=2026-01-01 --au=2026-12-31
 */
class ControlerComptabilite extends Command
{
    protected $signature = 'compta:controler
                            {--du= : début de période (AAAA-MM-JJ), par défaut le 1er janvier de l\'an dernier}
                            {--au= : fin de période (AAAA-MM-JJ), par défaut aujourd\'hui}';

    protected $description = "Recoupe la TVA collectée et les marges de transport avec les données d'origine.";

    /** Nombre de points à vérifier par un humain. */
    private int $problemes = 0;

    public function handle()
    {
        $du = $this->option('du') ?: now()->subYear()->startOfYear()->format('Y-m-d');
        $au = $this->option('au') ?: now()->format('Y-m-d');

        $this->newLine();
        $this->info("Contrôle comptable du {$du} au {$au}");

        $compta = new ComptabiliteController();

        $this->controlerTva($compta, $du, $au);
        $this->controlerTransport($compta, $du, $au);
        $this->controlerFournisseurs();

        $this->newLine();
        if ($this->problemes === 0) {
            $this->info('Comptabilité cohérente : aucun écart détecté.');
            return self::SUCCESS;
        }

        $this->warn("{$this->problemes} point(s) à vérifier — détail ci-dessus.");
        return self::SUCCESS;
    }

    // ================================================================== TVA

    private function controlerTva(ComptabiliteController $compta, string $du, string $au): void
    {
        $this->newLine();
        $this->line('1. TVA COLLECTÉE');

        $etat = $compta->tvaCollectee(
            Request::create('/', 'GET', ['du' => $du, 'au' => $au])
        )->getData();

        $lignes  = $etat['lignes'];
        $totaux  = $etat['totaux'];
        $taux    = (float) (Configuration::first()?->tva ?? 18);

        $this->table(
            ['Indicateur', 'Montant'],
            [
                ['Base hors taxe',    $this->fr($totaux->base_ht)],
                ['TVA facturée',      $this->fr($totaux->tva_facturee)],
                ['TVA encaissée',     $this->fr($totaux->tva_encaissee)],
                ['Reste à encaisser', $this->fr($totaux->tva_a_encaisser)],
            ]
        );

        // -- a) aucune écriture ne doit se perdre entre la table et l'état.
        $brut = (float) TvaCommande::whereHas('commande', fn ($q) => $q
                ->whereDate('date_commande', '>=', $du)
                ->whereDate('date_commande', '<=', $au))
            ->sum('montant');

        if (abs($brut - $totaux->tva_facturee) >= 1) {
            $this->signaler(
                "La somme brute des écritures de TVA sur la période ({$this->fr($brut)}) "
                . "diffère du total de l'état ({$this->fr($totaux->tva_facturee)}). "
                . "Des écritures pointent probablement vers un document supprimé."
            );
        }

        // -- b) taux effectif conforme au taux paramétré.
        foreach ($lignes->where('anomalie', true) as $l) {
            $this->signaler(sprintf(
                "Facture %s (%s) : taux effectif %.2f %% au lieu de %.2f %%. "
                . "Base %s pour %s de taxe — la base a changé après le calcul.",
                $l->numero, $l->service, $l->taux, $taux,
                $this->fr($l->base_ht), $this->fr($l->tva_facturee)
            ));
        }

        // -- c) la TVA encaissée ne peut pas dépasser la TVA facturée.
        if ($totaux->tva_encaissee > $totaux->tva_facturee + 1) {
            $this->signaler(
                "TVA encaissée ({$this->fr($totaux->tva_encaissee)}) supérieure à la TVA facturée "
                . "({$this->fr($totaux->tva_facturee)}) : un règlement dépasse le net à payer."
            );
        }

        // -- d) une facture réglée sans écriture de TVA.
        $sansTva = Commande::whereDate('date_commande', '>=', $du)
            ->whereDate('date_commande', '<=', $au)
            ->whereNull('deleted_at')
            ->whereDoesntHave('TvaCommande')
            ->get()
            ->filter(fn (Commande $c) => $c->montantPayeComptant() > 0);

        foreach ($sansTva as $c) {
            $this->signaler(
                "Commande {$c->numero} encaissée ({$this->fr($c->montantPayeComptant())}) "
                . "sans aucune écriture de TVA."
            );
        }
    }

    // ============================================================ TRANSPORT

    private function controlerTransport(ComptabiliteController $compta, string $du, string $au): void
    {
        $this->newLine();
        $this->line('2. MARGES SUR LE TRANSPORT');

        $etat = $compta->beneficesLivraisons(
            Request::create('/', 'GET', ['du' => $du, 'au' => $au])
        )->getData();

        $lignes = $etat['lignes'];
        $totaux = $etat['totaux'];

        $this->table(
            ['Indicateur', 'Valeur'],
            [
                ['Livraisons retenues',      (string) $totaux->nb],
                ['Retraits sur place exclus', (string) $etat['retraitsExclus']],
                ['Transport facturé',        $this->fr($totaux->facture)],
                ['Versé aux livreurs',       $this->fr($totaux->verse)],
                ['Marge',                    $this->fr($totaux->marge)],
                ['Taux de marge',            is_null($totaux->taux)
                    ? 'n/a' : number_format($totaux->taux, 1, ',', ' ') . ' %'],
            ]
        );

        // -- a) courses vendues à perte.
        foreach ($lignes->where('perte', true) as $l) {
            $this->signaler(sprintf(
                "Livraison %s (%s, %s) vendue à perte : facturé %s, versé %s à %s.",
                $l->numero, $l->service, $l->document,
                $this->fr($l->facture), $this->fr($l->verse), $l->livreur
            ));
        }

        // -- b) le transport réparti doit égaler celui porté par les documents.
        $attendu = 0.0;
        $vus     = [];
        foreach (Livraison::with('detailCommande.commande')->whereNull('deleted_at')->get() as $l) {
            $c = $l->detailCommande?->commande;
            if (!$c || !$c->id || (int) $c->est_livrable !== 1 || isset($vus[$c->id])) {
                continue;
            }
            $date = $c->date_commande ?? $c->created_at;
            if (!$date || $date < $du || $date > $au . ' 23:59:59') {
                continue;
            }
            $vus[$c->id] = true;
            $attendu += (float) $c->cout_livraison_client;
        }

        if (abs($attendu - $totaux->facture) >= 2) {
            $this->signaler(
                "Transport réparti ({$this->fr($totaux->facture)}) différent du transport porté par "
                . "les commandes livrables ({$this->fr($attendu)}) : une livraison est orpheline, "
                . "ou une commande livrable n'a aucune livraison."
            );
        }

        // -- c) livraison sans livreur : personne à payer, marge illusoire.
        $sansLivreur = $lignes->filter(fn ($l) => $l->livreur === '-');
        if ($sansLivreur->count() > 0) {
            $this->signaler(sprintf(
                "%d livraison(s) sans livreur affecté (%s) : leur marge est celle du transport "
                . "facturé, aucune course n'ayant été payée.",
                $sansLivreur->count(),
                $sansLivreur->pluck('numero')->take(5)->implode(', ')
            ));
        }

        // -- d) livraison facturée sans distance : le tarif au km n'a pas pu jouer.
        $sansDistance = $lignes->filter(fn ($l) => $l->facture > 0 && $l->distance <= 0);
        if ($sansDistance->count() > 0) {
            $this->signaler(sprintf(
                "%d livraison(s) facturée(s) sans distance renseignée (%s) : "
                . "un tarif au kilomètre ne peut pas s'y appliquer.",
                $sansDistance->count(),
                $sansDistance->pluck('numero')->take(5)->implode(', ')
            ));
        }
    }

    // ========================================================= FOURNISSEURS

    private function controlerFournisseurs(): void
    {
        $this->newLine();
        $this->line('3. TVA REVERSÉE AUX FOURNISSEURS');

        $enlevements = Enlevement::with('fournisseur')->whereNull('deleted_at')->get();

        $assujettis = $enlevements->filter(fn (Enlevement $e) => (bool) $e->fournisseur?->assujetti_tva);
        $tvaDeductible = $assujettis->sum(fn (Enlevement $e) => $e->tvaFournisseur());

        $this->table(
            ['Indicateur', 'Valeur'],
            [
                ["Bons d'enlèvement",           (string) $enlevements->count()],
                ['dont fournisseurs assujettis', (string) $assujettis->count()],
                ['TVA déductible correspondante', $this->fr($tvaDeductible)],
            ]
        );

        if ($assujettis->isEmpty()) {
            $this->line("   Aucun fournisseur assujetti : la TVA collectée est due en totalité,");
            $this->line("   il n'y a rien à déduire.");
            return;
        }

        // Un fournisseur assujetti sans document fiscal est un risque de contrôle.
        foreach ($assujettis->pluck('fournisseur')->unique('id')->filter() as $f) {
            if (empty($f->dfe) && empty($f->registre_commerce)) {
                $this->signaler(
                    "Fournisseur « {$f->nom_prenoms} » est marqué assujetti à la TVA mais n'a "
                    . "ni DFE ni registre de commerce enregistré : la TVA déduite serait "
                    . "difficile à justifier."
                );
            }
        }
    }

    // ================================================================ Outils

    private function signaler(string $message): void
    {
        $this->problemes++;
        $this->warn('   • ' . $message);
    }

    private function fr(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' F';
    }
}
