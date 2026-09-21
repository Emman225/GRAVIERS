<?php

namespace App\Services;

use App\Models\Enlevement;
use App\Models\Fournisseur;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * LE PLANNING D'ENLÈVEMENT DU FOURNISSEUR (lot 83, 15/09/2026).
 *
 * Transposition du classeur « Planning_Enlevements.xlsx » remis par le client :
 *  - PLANNING : par jour (31 jours à partir d'une date) et par produit, les
 *    quantités des bons PRÉVUS (pas encore servis), le nombre de bons du jour
 *    et le total ; totaux de la période en tête.
 *  - HISTORIQUE : la même grille pour les bons ENLEVÉS (servis), à la date de
 *    la validation du fournisseur, avec la quantité réellement servie.
 *  - RECAP_PRODUITS : par produit, quantité totale, enlevé (cumul), prévu (à
 *    venir), reste à enlever, % enlevé, nombre de bons enlevés et prévus.
 *
 * Correspondance avec le site : un « bon » du classeur est un enlèvement ;
 * « Prévu » = qte_servi vide sur une livraison acceptée (les bons en attente),
 * « Enlevé » = qte_servi renseigné (les bons traités). La « quantité totale à
 * enlever » du classeur (paramètre saisi) est ici DÉDUITE : stock disponible
 * (stock_produit.qte, déjà diminué des bons réservés par le gestionnaire)
 * + prévu + enlevé ; le reste à enlever = stock disponible + prévu.
 */
class PlanningFournisseur
{
    public const JOURS = 31;

    /** Les bons prévus : pas encore servis, sur une livraison acceptée. */
    public static function bonsPrevus(Fournisseur $fournisseur): Collection
    {
        return Enlevement::with(['produit', 'livraison.livreur.user', 'livraison.clientLivreur', 'livraison.client'])
            ->where('fournisseur_id', $fournisseur->id)
            ->whereNull('qte_servi')
            ->whereHas('livraison', fn ($q) => $q->where('accepte', 1))
            ->get();
    }

    /** Les bons enlevés : quantité servie renseignée. */
    public static function bonsEnleves(Fournisseur $fournisseur): Collection
    {
        return Enlevement::with(['produit', 'livraison.livreur.user', 'livraison.clientLivreur', 'livraison.client'])
            ->where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('qte_servi')
            ->get();
    }

    /** La date d'un bon : celle de la livraison prévue, ou celle de la validation pour un bon servi. */
    public static function dateDuBon(Enlevement $bon, bool $servi): ?Carbon
    {
        $valeur = $servi
            ? ($bon->fournisseur_validation ?: $bon->updated_at)
            : ($bon->livraison?->date_livraison ?: $bon->created_at);
        if (!$valeur) {
            return null;
        }
        try {
            return Carbon::parse($valeur)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** La quantité d'un bon : servie s'il l'a été, demandée sinon. */
    public static function quantiteDuBon(Enlevement $bon, bool $servi): float
    {
        return (float) ($servi ? ($bon->qte_servi ?? 0) : ($bon->qte ?? 0));
    }

    /** L'unité d'un produit, quel que soit l'endroit où elle est écrite. */
    public static function uniteDe($produit): string
    {
        if (!$produit) {
            return '';
        }
        $unite = null;
        if (method_exists($produit, 'uniteProduit')) {
            $unite = $produit->uniteProduit?->abreviation ?? $produit->uniteProduit?->libelle ?? null;
        }

        return (string) ($unite ?: ($produit->unite ?? ''));
    }

    /** Qui enlève : le livreur, ou le client qui retire lui-même. */
    public static function quiEnleve(Enlevement $bon): string
    {
        $livraison = $bon->livraison;
        if (!$livraison) {
            return '-';
        }
        if ((int) $livraison->livre_par === 2) {
            return trim((string) ($livraison->clientLivreur?->nom ?? $livraison->client?->display_name ?? 'Client')) ?: 'Client';
        }

        return (string) ($livraison->livreur?->user?->nom_prenoms ?: '-');
    }

    /**
     * Les colonnes de produits : ceux qui ont au moins un bon dans la liste
     * donnée. Le stock entier faisait une colonne par produit jamais commandé
     * (25 colonnes vides pour un fournisseur en ligne) et un PDF illisible ;
     * avec $toutLeStock, les produits du stock s'ajoutent (récap).
     */
    public static function produits(Fournisseur $fournisseur, Collection $bons, bool $toutLeStock = false): array
    {
        $produits = [];
        if ($toutLeStock) {
            foreach ($fournisseur->produits as $p) {
                $produits[$p->id] = ['id' => $p->id, 'nom' => $p->nom, 'unite' => self::uniteDe($p)];
            }
        }
        foreach ($bons as $bon) {
            if ($bon->produit && !isset($produits[$bon->produit->id])) {
                $produits[$bon->produit->id] = ['id' => $bon->produit->id, 'nom' => $bon->produit->nom, 'unite' => self::uniteDe($bon->produit)];
            }
        }
        usort($produits, fn ($a, $b) => strcmp((string) $a['nom'], (string) $b['nom']));

        return array_values($produits);
    }

    /**
     * La grille du classeur : JOURS lignes à partir de $debut, une colonne par
     * produit, « Nb bons » et « Total quantités » ; les totaux de la période.
     */
    public static function grille(Fournisseur $fournisseur, Collection $bons, Carbon $debut, bool $servi): array
    {
        $debut    = $debut->copy()->startOfDay();
        $fin      = $debut->copy()->addDays(self::JOURS - 1);
        $produits = self::produits($fournisseur, $bons);

        $jours = [];
        for ($i = 0; $i < self::JOURS; $i++) {
            $jour = $debut->copy()->addDays($i);
            $jours[$jour->toDateString()] = [
                'date'      => $jour,
                'quantites' => array_fill_keys(array_column($produits, 'id'), 0.0),
                'nb'        => 0,
                'total'     => 0.0,
                'bons'      => [],
            ];
        }

        foreach ($bons as $bon) {
            $date = self::dateDuBon($bon, $servi);
            if (!$date || $date->lt($debut) || $date->gt($fin)) {
                continue;
            }
            $cle = $date->toDateString();
            $qte = self::quantiteDuBon($bon, $servi);
            $pid = $bon->produit_id;
            if (isset($jours[$cle]['quantites'][$pid])) {
                $jours[$cle]['quantites'][$pid] += $qte;
            }
            $jours[$cle]['nb']    += 1;
            $jours[$cle]['total'] += $qte;
            $jours[$cle]['bons'][] = $bon;
        }

        $totaux = array_fill_keys(array_column($produits, 'id'), 0.0);
        $nbTotal = 0;
        $totalGeneral = 0.0;
        foreach ($jours as $j) {
            foreach ($j['quantites'] as $pid => $q) {
                $totaux[$pid] += $q;
            }
            $nbTotal      += $j['nb'];
            $totalGeneral += $j['total'];
        }

        return [
            'debut'        => $debut,
            'fin'          => $fin,
            'produits'     => $produits,
            'jours'        => array_values($jours),
            'totaux'       => $totaux,
            'nbTotal'      => $nbTotal,
            'totalGeneral' => $totalGeneral,
        ];
    }

    /** Le récapitulatif par produit, sur tout ce que porte la base. */
    public static function recap(Fournisseur $fournisseur): array
    {
        $prevus  = self::bonsPrevus($fournisseur);
        $enleves = self::bonsEnleves($fournisseur);
        $stocks  = [];
        foreach ($fournisseur->produits as $p) {
            $stocks[$p->id] = (float) ($p->pivot->qte ?? 0);
        }

        $lignes = [];
        foreach (self::produits($fournisseur, $prevus->merge($enleves), true) as $p) {
            $enleve   = (float) $enleves->where('produit_id', $p['id'])->sum(fn ($b) => self::quantiteDuBon($b, true));
            $prevu    = (float) $prevus->where('produit_id', $p['id'])->sum(fn ($b) => self::quantiteDuBon($b, false));
            $stock    = (float) ($stocks[$p['id']] ?? 0);
            $reste    = $stock + $prevu;
            $total    = $reste + $enleve;
            $lignes[] = [
                'produit'       => $p['nom'],
                'unite'         => $p['unite'],
                'total'         => $total,
                'enleve'        => $enleve,
                'prevu'         => $prevu,
                'stock'         => $stock,
                'reste'         => $reste,
                'pourcentage'   => $total > 0 ? round($enleve / $total * 100, 1) : null,
                'nbEnleves'     => $enleves->where('produit_id', $p['id'])->count(),
                'nbPrevus'      => $prevus->where('produit_id', $p['id'])->count(),
            ];
        }

        return $lignes;
    }
}
