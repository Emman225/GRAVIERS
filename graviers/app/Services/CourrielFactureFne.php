<?php

namespace App\Services;

use App\Mail\DocumentPdfMail;
use App\Models\Facture;
use Help;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PDF;

/**
 * LA FACTURE CERTIFIÉE PART AU CLIENT (lot 93, 16/09/2026) : facture de vente,
 * de location, de transport dès sa certification par la DGI, facture d'avoir
 * dès son émission. En PDF, une seule fois par document (facture.courriel_envoye_le),
 * après la réponse HTTP, jamais bloquant : un courriel qui échoue n'annule ni la
 * certification ni l'avoir.
 */
class CourrielFactureFne
{
    /** Le libellé du document, tel qu'il figure dans l'objet du courriel. */
    public static function typeDocument(Facture $facture): string
    {
        if ($facture->estUnAvoir()) {
            return "Facture d'avoir";
        }
        if ((string) $facture->service === (string) Help::$LOCATION) {
            return 'Facture de location';
        }
        if ((string) $facture->service === (string) Help::$LIVRAISON) {
            return 'Facture de transport';
        }

        return 'Facture de vente';
    }

    /** Le PDF du document, par le même chemin que l'écran : celui de la DGI dès que la facture est certifiée (lot 96). */
    public static function pdf(Facture $facture)
    {
        if ($facture->isCertifiedFne() && ($dgi = DocumentDgi::pdf($facture))) {
            return $dgi;
        }

        return self::pdfLocal($facture);
    }

    /** L'ancien document, dessiné par le site : repli sans données DGI. */
    public static function pdfLocal(Facture $facture)
    {
        if ($facture->estUnAvoir()) {
            return FactureAvoir::pdf($facture);
        }
        if (in_array((string) $facture->service, [(string) Help::$LOCATION, (string) Help::$LIVRAISON], true)) {
            return DocumentDAffaire::pdfFacture($facture);
        }

        return PDF::loadView('document.factureCommande', FacturationCommande::donneesDocument($facture))
            ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait']);
    }

    public static function nomFichier(Facture $facture): string
    {
        if ($facture->isCertifiedFne() && is_array($facture->fne_verification_payload ?? null)) {
            return DocumentDgi::nomFichier($facture);
        }
        if ($facture->estUnAvoir()) {
            return 'Facture_Avoir_' . $facture->numero . '.pdf';
        }
        if (in_array((string) $facture->service, [(string) Help::$LOCATION, (string) Help::$LIVRAISON], true)) {
            return DocumentDAffaire::nomFichierFacture($facture);
        }

        return 'Facture_' . $facture->numero . '.pdf';
    }

    /**
     * Programme l'envoi (ou l'exécute si $immediat). Vrai quand un envoi est
     * programmé ; faux si déjà envoyé, non certifié, ou sans adresse.
     */
    public static function envoyer(Facture $facture, bool $immediat = false, bool $forcer = false): bool
    {
        try {
            $facture = Facture::find($facture->id) ?: $facture;
            if (!$facture->isCertifiedFne()) {
                return false;
            }
            $memoire = Schema::hasColumn('facture', 'courriel_envoye_le');
            if ($memoire && $facture->courriel_envoye_le && !$forcer) {
                return false;
            }
            $client = $facture->client;
            $email  = $client?->user?->email ?: ($client?->email ?? null);
            if (!$email) {
                return false;
            }
            // La date est posée AVANT l'envoi (un second chemin concurrent se
            // tait), effacée si l'envoi échoue.
            if ($memoire) {
                Facture::where('id', $facture->id)->update(['courriel_envoye_le' => now()]);
            }
            $type = self::typeDocument($facture);
            $envoi = function () use ($facture, $client, $email, $type, $memoire) {
                try {
                    Mail::send(new DocumentPdfMail(
                        (string) ($client?->display_name ?? 'Client'),
                        $email,
                        $type,
                        (string) ($facture->fne_reference ?: $facture->numero),
                        self::pdf($facture)->output(),
                        self::nomFichier($facture)
                    ));
                } catch (\Throwable $e) {
                    Log::warning($type . ' ' . $facture->numero . ' non envoyée : ' . $e->getMessage());
                    if ($memoire) {
                        Facture::where('id', $facture->id)->update(['courriel_envoye_le' => null]);
                    }
                }
            };
            if ($immediat || app()->runningInConsole()) {
                $envoi();
            } else {
                app()->terminating($envoi);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Courriel de la facture ' . ($facture->numero ?? '?') . ' : ' . $e->getMessage());

            return false;
        }
    }
}
