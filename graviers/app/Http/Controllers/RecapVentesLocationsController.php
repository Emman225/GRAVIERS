<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Enlevement;
use App\Models\Location;
use Illuminate\Http\Request;

/**
 * LES DEUX RÉCAPITULATIFS COMPTABLES : ce qu'on a vendu, ce qu'on a loué.
 *
 * « CA détaillé » répond par produit : combien de sable est parti, pour quelle
 * marge. Ces deux écrans répondent par AFFAIRE : quelle vente, à quel client,
 * encaissée ou non. Ce sont deux lectures différentes des mêmes faits, et la
 * comptabilité a besoin de la seconde — on ne relance pas un produit, on
 * relance un client sur une vente.
 *
 * Côté locations, il n'existait rien du tout.
 *
 * L'axe de temps diffère volontairement entre les deux écrans :
 *   - une VENTE se rattache au bon servi par le fournisseur, seule date à
 *     laquelle la marchandise a réellement bougé — c'est l'axe déjà retenu par
 *     « CA par famille » et « CA détaillé », et deux axes concurrents dans le
 *     même back-office produiraient deux chiffres d'affaires ;
 *   - une LOCATION se rattache à sa date de location, puisqu'aucun bon
 *     fournisseur n'y correspond.
 */
class RecapVentesLocationsController extends Controller
{
    /** Le premier et le dernier jour du mois en cours, si rien n'est demandé. */
    private function periode(Request $request): array
    {
        return [
            $request->input('du') ?: now()->startOfMonth()->format('Y-m-d'),
            $request->input('au') ?: now()->endOfMonth()->format('Y-m-d'),
        ];
    }

    /**
     * RÉCAPITULATIF DES VENTES.
     *
     * Une ligne par commande : ce qu'elle a rapporté, ce qu'elle a coûté en
     * achats, ce qui reste à encaisser.
     */
    public function ventes(Request $request)
    {
        [$du, $au] = $this->periode($request);

        // Les bons SERVIS de la période : ils portent à la fois la date qui
        // fait foi et le coût d'achat réel.
        // La règle de ce qui compte comme une vente tient dans
        // App\Support\BonsDeVente : « CA détaillé » et « CA par famille »
        // appellent la même, pour que les trois écrans ne divergent plus.
        $bons = \App\Support\BonsDeVente::servis($du, $au)
            ->with(['livraison.detailCommande', 'produit'])
            ->get();

        $parCommande = [];

        // LES BONS QU'ON NE SAIT PAS RATTACHER.
        //
        // Un bon servi dont la livraison ne pointe plus vers sa ligne de
        // commande n'a pas de vente à laquelle s'accrocher — ni, surtout, de
        // PRIX DE VENTE. On lui prêtait celui du catalogue : une recette
        // jamais facturée à personne, qui gonflait le chiffre d'affaires puis,
        // moins un coût bien réel, écrasait la marge des vraies ventes.
        //
        // Ils sont donc écartés des totaux, et le bandeau les annonce : leur
        // nombre et ce qu'ils ont coûté. Les trois écrans du chiffre
        // d'affaires appliquent désormais cette même règle.
        $orphelins = ['cout' => 0.0, 'lignes' => 0];

        foreach ($bons as $bon) {
            $detail = optional($bon->livraison)->detailCommande;
            $prix   = \App\Support\BonsDeVente::prixFacture($bon);

            if (!$detail || !$detail->commande_id || $prix === null) {
                $orphelins['cout']  += $bon->montantHt();
                $orphelins['lignes']++;
                continue;
            }

            $id = (int) $detail->commande_id;

            if (!isset($parCommande[$id])) {
                $parCommande[$id] = ['vente' => 0.0, 'cout' => 0.0, 'lignes' => 0];
            }

            $qte = $bon->quantiteAPayer();

            $parCommande[$id]['vente'] += $qte * $prix;
            $parCommande[$id]['cout']  += $bon->montantHt();
            $parCommande[$id]['lignes']++;
        }

        $commandes = Commande::with(['client', 'factures'])
            ->whereIn('id', array_keys($parCommande))
            ->get()
            ->keyBy('id');

        $lignes = [];

        foreach ($parCommande as $id => $chiffres) {
            $commande = $commandes->get($id);

            if (!$commande) {
                continue;
            }

            $encaisse = $commande->montantPayeComptant();

            $lignes[] = (object) [
                'id'        => $id,
                'numero'    => $commande->numero,
                'date'      => $commande->date_commande,
                'client'    => $commande->client?->nom_prenoms ?: '—',
                'lignes'    => $chiffres['lignes'],
                'vente'     => $chiffres['vente'],
                'cout'      => $chiffres['cout'],
                'marge'     => $chiffres['vente'] - $chiffres['cout'],
                'facture'   => $commande->montantAPayer(),
                'encaisse'  => $encaisse,
                'reste'     => max(0, $commande->montantAPayer() - $encaisse),
            ];
        }

        usort($lignes, fn ($a, $b) => strcmp((string) $b->date, (string) $a->date));

        // LES ORPHELINS NE FONT PAS UNE LIGNE DU TABLEAU.
        //
        // Ils y figuraient, avec une recette inventée. Le total du tableau,
        // celui du bandeau et celui de « CA détaillé » disaient alors trois
        // chiffres différents. Le bandeau d'alerte, lui, les nomme et les
        // chiffre : rien n'est caché, et chaque colonne s'additionne.

        return view('comptabilite.recapVentes', [
            'lignes'   => $lignes,
            'du'       => $du,
            'au'       => $au,
            'totaux'   => (object) [
                'vente'    => array_sum(array_map(fn ($l) => $l->vente, $lignes)),
                'cout'     => array_sum(array_map(fn ($l) => $l->cout, $lignes)),
                'marge'    => array_sum(array_map(fn ($l) => $l->marge, $lignes)),

                // LE BANDEAU MESURE LES VENTES, PAS LES ANOMALIES.
                //
                // Un bon orphelin n'a pas de prix de vente connu : sa ligne
                // facturée a disparu. On lui prêtait celui du catalogue, donc
                // un chiffre d'affaires jamais facturé à personne — et cette
                // fausse recette, moins un coût bien réel, écrasait la marge
                // des vraies ventes. Constaté le 03/09/2026 : les orphelins
                // portaient -735 500 quand les ventes rattachées dégageaient
                // +3 480.
                //
                // Les lignes du tableau, elles, ne bougent pas : leur total
                // continue de correspondre à celui du « CA détaillé ».
                'venteRattachee' => array_sum(array_map(
                    fn ($l) => empty($l->orphelin) ? $l->vente : 0, $lignes)),
                'coutRattache'   => array_sum(array_map(
                    fn ($l) => empty($l->orphelin) ? $l->cout : 0, $lignes)),
                'margeRattachee' => array_sum(array_map(
                    fn ($l) => empty($l->orphelin) ? $l->marge : 0, $lignes)),
                'coutOrphelins'  => $orphelins['cout'],
                'bonsOrphelins'  => $orphelins['lignes'],

                'facture'  => array_sum(array_map(fn ($l) => $l->facture, $lignes)),
                'encaisse' => array_sum(array_map(fn ($l) => $l->encaisse, $lignes)),
                'reste'    => array_sum(array_map(fn ($l) => $l->reste, $lignes)),
            ],
        ]);
    }

    /**
     * RÉCAPITULATIF DES LOCATIONS.
     *
     * Une ligne par location : le matériel, la durée, ce qui a été encaissé, et
     * la caution — qui n'est pas un produit et ne doit jamais être comptée
     * comme tel.
     */
    /**
     * ÉTAT DES CAUTIONS DE LOCATION.
     *
     * Une caution n'est pas un produit : c'est un dépôt que DALAKOUN détient et
     * doit rendre. Deux montants seulement intéressent la comptabilité, et rien
     * ne les rapprochait jusqu'ici :
     *
     *   · la RETENUE — ce qui reste acquis à l'entreprise, en réparation d'un
     *     dommage ou d'un retard. C'est un produit, et il doit se déclarer ;
     *   · le RESTITUÉ — ce qui repart chez le client. C'est une dette éteinte,
     *     et elle doit se justifier.
     *
     * Le restitué se CALCULE (caution moins retenue) plutôt que de se lire :
     * aucune colonne ne le stocke, et `caution_restituee` n'est qu'un drapeau
     * — vrai dès qu'une PARTIE est rendue. S'y fier donnerait un état faux dès
     * la première retenue partielle.
     *
     * Les locations non encore rendues sont montrées à part : leur caution est
     * détenue, ni retenue ni restituée. La confondre avec du restitué gonflerait
     * les sorties d'une somme qui n'a pas bougé.
     */
    public function cautions(Request $request)
    {
        [$du, $au] = $this->periode($request);

        // La date de RETOUR fait foi, pas celle de la location : c'est au retour
        // du matériel que la caution se dénoue, et c'est cette date-là que la
        // comptabilité rapproche de ses écritures. Les locations non rendues
        // n'ont pas de date de retour : on les prend sur leur date de location,
        // pour que le détenu du mois apparaisse.
        $locations = Location::with('client')
            // `location.statut` N'EST PAS UN INDICATEUR D'ACTIVITÉ.
            //
            // Il porte l'état du RÈGLEMENT : 1 = aucun paiement, 2 = acompte,
            // 3 = soldé. Filtrer sur `STATUT_ACTIF` (= 1) ne retenait donc que
            // les locations JAMAIS PAYÉES — soit exactement celles qui ne
            // peuvent pas figurer ici. Une location ne se valide qu'une fois
            // soldée, et c'est la validation qui enregistre la caution : cet
            // écran était structurellement vide, quelles que soient les dates.
            //
            // Constaté le 28/08/2026 en production : « Aucune caution sur cette
            // période », sur toutes les périodes.
            //
            // Le bon critère est l'annulation, comme partout ailleurs dans le
            // projet (`etat_location <> 'ANNULEE'`). Les locations effacées sont
            // déjà écartées : le modèle pratique la suppression douce.
            ->where('etat_location', '<>', 'ANNULEE')
            ->where('caution', '>', 0)
            ->where(function ($q) use ($du, $au) {
                $q->where(function ($rendues) use ($du, $au) {
                    $rendues->whereNotNull('date_retour')
                        ->whereDate('date_retour', '>=', $du)
                        ->whereDate('date_retour', '<=', $au);
                })->orWhere(function ($enCours) use ($du, $au) {
                    $enCours->whereNull('date_retour')
                        ->whereDate('date_location', '>=', $du)
                        ->whereDate('date_location', '<=', $au);
                });
            })
            ->orderByDesc('date_retour')
            ->orderByDesc('date_location')
            ->get();

        $lignes = [];

        foreach ($locations as $location) {
            $caution = (float) ($location->caution ?? 0);
            $retenue = (float) ($location->caution_retenue ?? 0);
            $rendue  = $location->date_retour !== null;

            // Le restitué ne se déduit QUE d'un matériel rendu. Tant que la
            // location court, la caution est simplement détenue.
            // Le transtypage n'est pas cosmetique : max(0, 0.0) rend l'ENTIER 0,
            // et l'etat melangeait alors des entiers et des flottants selon le
            // sort de la caution. Un total s'en accommode, une comparaison non.
            $restitue = $rendue ? (float) max(0, $caution - $retenue) : 0.0;

            $lignes[] = [
                'numero'      => $location->numero,
                'client'      => $location->client?->display_name ?? '-',
                'date_retour' => $location->date_retour,
                'etat'        => $location->etat_location,
                'caution'     => $caution,
                'retenue'     => $rendue ? $retenue : 0.0,
                'restitue'    => $restitue,
                'detenue'     => $rendue ? 0.0 : $caution,
                'motif'       => $location->motif_retenue,
            ];
        }

        return view('comptabilite.etatCautions', [
            'lignes'         => $lignes,
            'du'             => $du,
            'au'             => $au,
            'totalCaution'   => array_sum(array_column($lignes, 'caution')),
            'totalRetenue'   => array_sum(array_column($lignes, 'retenue')),
            'totalRestitue'  => array_sum(array_column($lignes, 'restitue')),
            'totalDetenue'   => array_sum(array_column($lignes, 'detenue')),
        ]);
    }

    public function locations(Request $request)
    {
        [$du, $au] = $this->periode($request);

        $locations = Location::with(['client', 'detailLocation.produit', 'paiementS'])
            // MÊME DÉFAUT QUE L'ÉTAT DES CAUTIONS, et même correction.
            //
            // `statut` est l'état du règlement, pas un indicateur d'activité.
            // Ce récapitulatif ne montrait donc que les locations JAMAIS
            // payées : le chiffre d'affaires des locations et la marge de
            // DALAKOUN étaient calculés sur les seules affaires qui n'ont
            // rien rapporté.
            ->where('etat_location', '<>', 'ANNULEE')
            ->whereDate('date_location', '>=', $du)
            ->whereDate('date_location', '<=', $au)
            ->orderByDesc('date_location')
            ->get();

        // LE COÛT DU MATÉRIEL LOUÉ.
        //
        // Rien n'enregistre, au moment de la location, ce que le fournisseur
        // facture pour son matériel : seul le prix client est conservé. On le
        // reconstitue donc à partir du prix d'achat EN VIGUEUR AUJOURD'HUI —
        // c'est une estimation, et l'écran le dit. Elle reste juste tant que
        // les prix d'achat ne bougent pas, et c'est la seule base disponible.
        $coutUnitaire = [];

        foreach ($locations as $location) {
            foreach ($location->detailLocation as $detail) {
                if (!isset($coutUnitaire[$detail->produit_id])) {
                    $coutUnitaire[$detail->produit_id] =
                        (float) (\App\Models\Produit::prixAchatDe($detail->produit_id) ?? 0);
                }
            }
        }

        $lignes = [];

        foreach ($locations as $location) {
            $details = $location->detailLocation;

            // Base du bénéfice : ce qui est facturé pour le MATÉRIEL, remise
            // déduite. Ni la TVA — qui n'est pas un produit — ni le transport,
            // dont la marge se lit sur « Bénéfices sur les livraisons » et
            // serait comptée deux fois ici.
            $loue = max(0, (float) $location->montant_total - (float) ($location->remise ?? 0));

            $cout = (float) $details->sum(
                fn ($d) => ($coutUnitaire[$d->produit_id] ?? 0) * (float) $d->qte * (float) $d->nombre_jour
            );

            $lignes[] = (object) [
                'loue'      => $loue,
                'cout'      => $cout,
                'marge'     => $loue - $cout,
                'id'        => $location->id,
                'numero'    => $location->numero,
                'date'      => $location->date_location,
                'client'    => $location->client?->nom_prenoms ?: '—',
                'materiels' => $details->map(fn ($d) => $d->produit?->nom)->filter()->unique()->implode(', ') ?: '—',
                // Une location de deux matériels sur cinq jours compte dix
                // jours-matériel : c'est ce qui se facture.
                'jours'     => (float) $details->sum(fn ($d) => (float) $d->nombre_jour * (float) $d->qte),
                'montant'   => $location->montantAPayer(),
                'encaisse'  => $location->montantPayeComptant(),
                'reste'     => $location->montantRestantDu(),
                'caution'   => (float) ($location->caution ?? 0),
                'rendue'    => (bool) $location->caution_restituee,
                'etat'      => $location->etatLibelle(),
            ];
        }

        return view('comptabilite.recapLocations', [
            'lignes' => $lignes,
            'du'     => $du,
            'au'     => $au,
            'totaux' => (object) [
                'jours'    => array_sum(array_map(fn ($l) => $l->jours, $lignes)),
                'loue'     => array_sum(array_map(fn ($l) => $l->loue, $lignes)),
                'cout'     => array_sum(array_map(fn ($l) => $l->cout, $lignes)),
                'marge'    => array_sum(array_map(fn ($l) => $l->marge, $lignes)),
                'montant'  => array_sum(array_map(fn ($l) => $l->montant, $lignes)),
                'encaisse' => array_sum(array_map(fn ($l) => $l->encaisse, $lignes)),
                'reste'    => array_sum(array_map(fn ($l) => $l->reste, $lignes)),
                // Les cautions encore détenues : de l'argent qui n'est pas à nous.
                'caution'  => array_sum(array_map(fn ($l) => $l->rendue ? 0 : $l->caution, $lignes)),
            ],
        ]);
    }
}
