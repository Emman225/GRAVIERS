<?php

namespace App\Services\Comptabilite;

use App\Models\Commande;
use App\Models\Configuration;
use App\Models\DemandeLivraison;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Models\Location;
use App\Models\TvaCommande;

/**
 * CE QUE FACTURE RÉELLEMENT UNE FACTURE, ligne par ligne, avec le produit de
 * chaque ligne — la matière première d'une écriture.
 *
 * Le numéro d'affaire désigne l'affaire, pas la facture : une commande livrée
 * en plusieurs fois donne plusieurs factures. On ne lit donc JAMAIS la
 * commande entière : les lignes viennent des enlèvements rattachés à CETTE
 * facture (ou, pour une ancienne facture établie sur règlement, des lignes de
 * la commande au prorata de ce que la facture couvre). Les lignes sortent dans
 * l'ordre exact de la déclaration à la DGI (FneService::build…Payload) : c'est
 * ce qui permet de rattacher un avoir, qui cite les articles certifiés par
 * leur rang, au produit de chaque article.
 */
class CompositionFacture
{
    public const PRODUIT   = 'PRODUIT';
    public const TRANSPORT = 'TRANSPORT';

    /**
     * @return array{
     *   affaire: mixed, numero_affaire: ?string, client: mixed,
     *   lignes: array<int,array{rubrique:string,produit:mixed,libelle:string,ht:float,taux:float}>,
     *   remise: float, tva: float, airsi: float, total: float, problemes: array<int,string>
     * }
     */
    public static function de(Facture $facture): array
    {
        if ($facture->estUnAvoir()) {
            return self::deLAvoir($facture);
        }

        switch ($facture->service) {
            case \Help::$LOCATION:
                return self::deLaLocation($facture);
            case \Help::$LIVRAISON:
                return self::deLaLivraison($facture);
            default:
                return self::deLaVente($facture);
        }
    }

    private static function vide(Facture $facture): array
    {
        return [
            'affaire' => null, 'numero_affaire' => null, 'client' => $facture->client,
            'lignes' => [], 'remise' => 0.0, 'tva' => 0.0,
            'airsi' => (float) ($facture->airsi_applique ?? 0),
            'total' => abs((float) $facture->montant), 'problemes' => [],
        ];
    }

    /** L'affaire, même supprimée depuis : la facture certifiée, elle, demeure. */
    private static function trouver(string $classe, $id)
    {
        $requete = $classe::query();
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($classe), true)) {
            $requete->withTrashed();
        }

        return $requete->find($id);
    }

    private static function tauxDuParametrage(): float
    {
        return (float) (Configuration::first()?->tva ?? 0);
    }

    // ------------------------------------------------------------------ vente

    private static function deLaVente(Facture $facture): array
    {
        $c = self::vide($facture);
        $commande = $facture->service_id ? self::trouver(Commande::class, $facture->service_id) : null;
        if (!$commande) {
            $c['problemes'][] = 'Commande introuvable pour cette facture.';

            return $c;
        }

        $c['affaire'] = $commande;
        $c['numero_affaire'] = (string) $commande->numero;
        $c['client'] = $commande->client ?: $facture->client;
        $taux = (float) \Help::tauxTvaAffaire($commande);

        $enlevements = Enlevement::where('facture_id', $facture->id)->orderBy('id')->get();

        if ($enlevements->isNotEmpty()) {
            foreach ($enlevements as $enlevement) {
                $detail = $enlevement->livraison?->detailCommande;
                $quantite = (float) ($enlevement->qte_servi ?? $enlevement->qte ?? $detail?->qte ?? 0);
                $produit = $enlevement->produit ?: $detail?->produit;
                $c['lignes'][] = [
                    'rubrique' => self::PRODUIT, 'produit' => $produit,
                    'libelle'  => $produit?->nom ?? 'Article',
                    'ht'       => \Help::arrondiFranc($quantite * (float) ($detail?->prix ?? 0)),
                    'taux'     => $taux,
                ];
            }
        } else {
            // Ancienne facture établie sur règlement : la commande, au prorata de ce que la facture couvre.
            $du = (float) $commande->montantAPayer();
            $part = $du > 0 ? min(1.0, (float) $facture->montant / $du) : 1.0;
            foreach ($commande->detailCommande as $detail) {
                $c['lignes'][] = [
                    'rubrique' => self::PRODUIT, 'produit' => $detail->produit,
                    'libelle'  => $detail->produit?->nom ?? 'Article',
                    'ht'       => \Help::arrondiFranc((float) $detail->qte * (float) $detail->prix * $part),
                    'taux'     => $taux,
                ];
            }
        }

        $transport = (float) ($facture->cout_livraison_applique ?? $commande->cout_livraison_client ?? 0);
        $tvaTransport = (float) ($facture->tva_transport_applique ?? $commande->tva_transport ?? 0);
        if ($transport > 0) {
            $c['lignes'][] = [
                'rubrique' => self::TRANSPORT, 'produit' => null, 'libelle' => 'Transport facturé',
                'ht' => $transport, 'taux' => $tvaTransport > 0 ? $taux : 0.0,
            ];
        }

        $c['remise'] = (float) ($facture->remise_appliquee ?? $commande->remise ?? 0);
        $marchandise = array_sum(array_map(fn ($l) => $l['rubrique'] === self::PRODUIT ? $l['ht'] : 0.0, $c['lignes']));
        $c['tva'] = \Help::arrondiFranc(max(0.0, $marchandise - $c['remise']) * $taux / 100) + $tvaTransport;

        return $c;
    }

    // ------------------------------------------------------------------ location

    private static function deLaLocation(Facture $facture): array
    {
        $c = self::vide($facture);
        $location = $facture->service_id ? self::trouver(Location::class, $facture->service_id) : null;
        if (!$location) {
            $c['problemes'][] = 'Location introuvable pour cette facture.';

            return $c;
        }

        $c['affaire'] = $location;
        $c['numero_affaire'] = (string) $location->numero;
        $c['client'] = $location->client ?: $facture->client;
        $taux = (float) \Help::tauxTvaAffaire($location);

        foreach ($location->detailLocation as $detail) {
            $jours = (int) ($detail->nombre_jour ?: 1);
            $c['lignes'][] = [
                'rubrique' => self::PRODUIT, 'produit' => $detail->produit,
                'libelle'  => ($detail->produit?->nom ?? 'Location') . ' (location ' . $jours . ' j)',
                'ht'       => \Help::arrondiFranc((float) ($detail->prix ?? 0)),   // detail_location.prix = total de la ligne
                'taux'     => $taux,
            ];
        }

        $transport = (float) ($facture->cout_livraison_applique ?? $location->cout_livraison_client ?? 0);
        $tvaTransport = (float) ($facture->tva_transport_applique ?? $location->tva_transport ?? 0);
        if ($transport > 0) {
            $c['lignes'][] = [
                'rubrique' => self::TRANSPORT, 'produit' => null, 'libelle' => 'Transport facturé',
                'ht' => $transport, 'taux' => $tvaTransport > 0 ? $taux : 0.0,
            ];
        }

        $c['remise'] = (float) ($facture->remise_appliquee ?? $location->remise ?? 0);
        $materiel = array_sum(array_map(fn ($l) => $l['rubrique'] === self::PRODUIT ? $l['ht'] : 0.0, $c['lignes']));
        $c['tva'] = \Help::arrondiFranc(max(0.0, $materiel - $c['remise']) * $taux / 100) + $tvaTransport;

        return $c;
    }

    // ------------------------------------------------------------------ demande de livraison

    private static function deLaLivraison(Facture $facture): array
    {
        $c = self::vide($facture);
        $demande = $facture->service_id ? self::trouver(DemandeLivraison::class, $facture->service_id) : null;
        if (!$demande) {
            $c['problemes'][] = 'Demande de livraison introuvable pour cette facture.';

            return $c;
        }

        $c['affaire'] = $demande;
        $c['numero_affaire'] = (string) $demande->numero;
        $c['client'] = $demande->client ?: $facture->client;

        $tva = (float) TvaCommande::where('commande_id', $demande->id)
            ->where('type_affaire', \Help::$LIVRAISON)->whereNull('deleted_at')->sum('montant');

        // Le client n'achète pas la marchandise transportée, il achète son acheminement : une seule ligne, de transport.
        $c['lignes'][] = [
            'rubrique' => self::TRANSPORT, 'produit' => null, 'libelle' => 'Prestation de transport',
            'ht' => (float) ($demande->montantTotal ?? 0), 'taux' => $tva > 0 ? self::tauxDuParametrage() : 0.0,
        ];
        $c['remise'] = (float) ($facture->remise_appliquee ?? 0);
        $c['tva'] = $tva;

        return $c;
    }

    // ------------------------------------------------------------------ avoir

    /**
     * Un avoir crédite des articles certifiés de la facture d'origine, cités
     * par leur identifiant FNE. Leur rang dans la facture certifiée est celui
     * des lignes de la composition d'origine : c'est ainsi qu'un article
     * retrouve son produit. Taxes, AIRSI et remise suivent la part créditée —
     * la règle même qui a fixé le montant de l'avoir (FactureAvoir::emettre).
     */
    private static function deLAvoir(Facture $avoir): array
    {
        $c = self::vide($avoir);
        $origine = $avoir->facture_origine_id ? Facture::withTrashed()->find($avoir->facture_origine_id) : null;
        if (!$origine || $origine->estUnAvoir()) {
            $c['problemes'][] = "Facture d'origine de l'avoir introuvable.";

            return $c;
        }

        $base = self::de($origine);
        $c['affaire'] = $base['affaire'];
        $c['numero_affaire'] = $base['numero_affaire'];
        $c['client'] = $base['client'];
        $c['problemes'] = $base['problemes'];
        $c['origine'] = $origine;

        $certifies = array_values((array) ($origine->fne_response_payload['invoice']['items'] ?? []));
        $rangParId = [];
        $htCertifie = 0.0;
        foreach ($certifies as $rang => $article) {
            $rangParId[(string) ($article['id'] ?? '')] = $rang;
            $htCertifie += (float) ($article['quantity'] ?? 0) * (float) ($article['amount'] ?? 0);
        }
        $memeDecoupage = count($certifies) === count($base['lignes']);

        $htCredite = 0.0;
        foreach ((array) ($avoir->lignes_avoir ?? []) as $ligne) {
            $ht = \Help::arrondiFranc((float) ($ligne['quantity'] ?? 0) * (float) ($ligne['amount'] ?? 0));
            $htCredite += $ht;
            $rang = $rangParId[(string) ($ligne['id'] ?? '')] ?? null;
            $source = ($memeDecoupage && $rang !== null) ? $base['lignes'][$rang] : null;

            // Repli : le libellé certifié commence par le nom du produit.
            if (!$source) {
                foreach ($base['lignes'] as $candidate) {
                    $nom = (string) ($candidate['produit']?->nom ?? '');
                    if ($nom !== '' && str_starts_with((string) ($ligne['description'] ?? ''), $nom)) {
                        $source = $candidate;
                        break;
                    }
                }
            }
            if (!$source && strtoupper((string) ($ligne['reference'] ?? '')) === 'LIVRAISON') {
                $source = ['rubrique' => self::TRANSPORT, 'produit' => null, 'taux' => 0.0];
            }

            $c['lignes'][] = [
                'rubrique' => $source['rubrique'] ?? self::PRODUIT,
                'produit'  => $source['produit'] ?? null,
                'libelle'  => (string) ($ligne['description'] ?? 'Article crédité'),
                'ht'       => $ht,
                'taux'     => (float) ($source['taux'] ?? 0),
            ];
        }

        $part = $htCertifie > 0 ? min(1.0, $htCredite / $htCertifie) : 1.0;
        $c['remise'] = \Help::arrondiFranc($base['remise'] * $part);
        $c['airsi']  = \Help::arrondiFranc($base['airsi'] * $part);
        $c['tva']    = \Help::arrondiFranc($base['tva'] * $part);

        return $c;
    }
}
