<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\DemandeLivraison;
use App\Models\Facture;
use App\Models\Location;
use App\Models\Paiement;
use Help;
use Illuminate\Support\Collection;
use PDF;

/**
 * LES DOCUMENTS D'UNE LOCATION OU D'UNE DEMANDE DE LIVRAISON DANS MON COMPTE
 * (lot 85, 15/09/2026), comme pour les ventes (DocumentDeCommande) :
 *  - la proforma de l'affaire, ou sa facture dès qu'un règlement en ligne est
 *    validé (même règle que la commande) ;
 *  - la liste de ses factures normalisées DGI, à voir ou télécharger.
 * Les gabarits sont ceux du site : orders/recapLocation (proforma location),
 * document/factureLivraison (transport), document/factureLocation (DGI).
 */
class DocumentDAffaire
{
    /** Un règlement en ligne validé sur l'affaire : sans agence ni caissier. */
    public static function estPayeeEnLigne(string $service, int $id): bool
    {
        return Paiement::where('service', $service)->where('service_id', $id)
            ->where('statut', 1)->whereNull('agence_id')->whereNull('caissier_id')->exists();
    }

    public static function typeLocation(Location $location): string
    {
        return self::estPayeeEnLigne(Help::$LOCATION, (int) $location->id) ? 'Facture' : 'Proforma';
    }

    public static function typeLivraison(DemandeLivraison $demande): string
    {
        return self::estPayeeEnLigne(Help::$LIVRAISON, (int) $demande->id) ? 'Facture' : 'Proforma';
    }

    public static function titreLocation(Location $location): string
    {
        return self::typeLocation($location) === 'Facture' ? 'Facture de location' : 'Proforma location';
    }

    public static function titreLivraison(DemandeLivraison $demande): string
    {
        return self::typeLivraison($demande) === 'Facture' ? 'Facture de transport' : 'Proforma transport';
    }

    /** La proforma / facture de la location, sur le gabarit du récapitulatif. */
    public static function pdfLocation(Location $location)
    {
        $location->loadMissing(['detailLocation.produit', 'tvaLocation', 'adresseLivraison', 'client']);

        return PDF::loadView('orders.recapLocation', [
            'location'     => $location,
            'config'       => Configuration::first(),
            'mode'         => $location->modeDePaiement?->libelle,
            'fne_date'     => $location->created_at ? $location->created_at->format('d/m/Y H:i:s') : null,
            'typeDocument' => self::titreLocation($location),
            'pourPdf'      => true,
        ])->setOptions(['isHTML5ParseEnabled' => true, 'defaultPaperOrientation' => 'portrait']);
    }

    /** La proforma / facture de la demande de livraison, sur le gabarit de la facture de transport. */
    public static function pdfLivraison(DemandeLivraison $demande)
    {
        $demande->loadMissing(['detailLivraison', 'priseEnCharge', 'destination', 'client']);
        $fne = FneService::getDonneesFne(null, $demande->client);
        $fne['fne_numero'] = (string) $demande->numero;
        $fne['fne_date']   = $demande->created_at ? $demande->created_at->format('d/m/Y H:i:s') : $fne['fne_date'];

        return PDF::loadView('document.factureLivraison', array_merge([
            'demande'      => $demande,
            'facture'      => new Facture(['numero' => $demande->numero]),
            'config'       => Configuration::first(),
            'typeDocument' => self::titreLivraison($demande),
        ], $fne))->setOptions(['isHTML5ParseEnabled' => true, 'defaultPaperOrientation' => 'portrait']);
    }

    /** Les factures DGI d'une affaire (location ou livraison). */
    public static function factures(string $service, int $id): Collection
    {
        return Facture::where('service', $service)->where('service_id', $id)->orderBy('id')->get();
    }

    /** Le PDF d'une facture DGI de location ou de transport (mêmes données que le back-office). */
    public static function pdfFacture(Facture $facture)
    {
        if ($facture->isCertifiedFne() && ($dgi = DocumentDgi::pdf($facture))) {
            return $dgi;
        }
        if ($facture->estUnAvoir()) {
            return FactureAvoir::pdf($facture);
        }
        if ((string) $facture->service === (string) Help::$LOCATION) {
            $location = Location::with('detailLocation.produit.uniteProduit', 'tvaLocation', 'adresseLivraison', 'client')
                ->find($facture->service_id);

            return PDF::loadView('document.factureLocation', array_merge([
                'location'  => $location,
                'facture'   => $facture,
                'config'    => Configuration::first(),
                'livraison' => 1,
            ], FneService::getDonneesFne($facture, $facture->client)));
        }
        if ((string) $facture->service === (string) Help::$LIVRAISON) {
            $demande = DemandeLivraison::with(['detailLivraison', 'priseEnCharge', 'destination', 'client'])
                ->find($facture->service_id);

            return PDF::loadView('document.factureLivraison', array_merge([
                'demande' => $demande,
                'facture' => $facture,
                'config'  => Configuration::first(),
            ], FneService::getDonneesFne($facture, $facture->client)));
        }

        return null;
    }

    public static function nomFichierFacture(Facture $facture): string
    {
        $prefixe = (string) $facture->service === (string) Help::$LOCATION ? 'Facture_Location_' : 'Facture_Transport_';

        return $prefixe . $facture->numero . '.pdf';
    }
}
