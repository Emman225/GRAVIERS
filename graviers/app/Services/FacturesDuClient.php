<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DemandeLivraison;
use App\Models\Facture;
use App\Models\Location;
use Help;

/**
 * LES FACTURES DGI D'UN CLIENT POUR L'APPLICATION (lot 95, 16/09/2026) : les
 * mêmes lignes que « Factures DGI » de Mon compte, à plat (ventes, locations,
 * transports, avoirs), avec l'état de certification et le lien de vérification.
 */
class FacturesDuClient
{
    public static function pourLApplication(Client $client): array
    {
        $lignes = [];
        foreach (Facture::where('client_id', $client->id)->orderByDesc('id')->get() as $f) {
            $lignes[] = [
                'id'                => (int) $f->id,
                'numero'            => (string) $f->numero,
                'reference'         => (string) ($f->fne_reference ?: ($f->numero_fne ?? '')),
                'type_document'     => $f->estUnAvoir() ? Facture::TYPE_AVOIR : Facture::TYPE_FACTURE,
                'libelle'           => CourrielFactureFne::typeDocument($f),
                'service'           => (string) $f->service,
                'service_id'        => (int) $f->service_id,
                'num_affaire'       => self::numeroDeLAffaire($f),
                'montant'           => (float) $f->montant,
                'date'              => Help::dateHeure($f->created_at),
                'certifiee'         => $f->isCertifiedFne(),
                'motif_avoir'       => $f->motif_avoir,
                'origine_numero'    => $f->estUnAvoir() ? (string) ($f->origine?->numero ?? '') : null,
                'lien_verification' => $f->fne_token,
            ];
        }

        return $lignes;
    }

    private static function numeroDeLAffaire(Facture $f): string
    {
        $service = (string) $f->service;
        if ($service === (string) Help::$LOCATION) {
            return (string) (Location::find($f->service_id)?->numero ?? '');
        }
        if ($service === (string) Help::$LIVRAISON) {
            return (string) (DemandeLivraison::find($f->service_id)?->numero ?? '');
        }

        return (string) (Commande::find($f->service_id)?->numero ?? '');
    }
}
