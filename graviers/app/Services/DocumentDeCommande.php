<?php

namespace App\Services;

use App\Mail\DocumentPdfMail;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Paiement;
use App\Models\Produit;
use Help;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PDF;

/**
 * LA PROFORMA OU LA FACTURE D'UNE COMMANDE (lot 81, 15/09/2026).
 *
 * Règle du client : « après avoir passé la commande, la proforma doit être
 * envoyée par mail et affichée dans le compte client ; si c'est une commande
 * où le client paie en ligne, c'est la facture qui est envoyée par mail et
 * affichée dans son compte ».
 *
 * Le document est UNIQUE : le gabarit client.commandeValidee — la page que
 * le client voit après sa commande — sert aussi au PDF du courriel et au
 * bouton de « Mon compte ». Son titre suit la règle : « Proforma » tant que
 * rien n'a été payé en ligne, « Facture de vente » dès qu'un règlement en
 * ligne est validé. Une commande qui attend son paiement en ligne ne reçoit
 * rien : sa facture partira au paiement confirmé (PaiementEnLigne).
 *
 * Cette facture-ci est le document remis au client à la commande ; la facture
 * normalisée DGI, elle, reste établie à l'enlèvement (FacturationCommande).
 *
 * Un seul envoi par document (commande.proforma_envoyee_le /
 * facture_envoyee_le, migration 2026_09_15_100000) ; différé après la réponse
 * et jamais bloquant : un courriel qui échoue n'empêche aucune opération.
 */
class DocumentDeCommande
{
    public const PROFORMA = 'Proforma';
    public const FACTURE  = 'Facture';

    /** Un règlement en ligne validé : sans agence ni caissier (cf. RecuPaiement::estEnLigne). */
    public static function estPayeeEnLigne(Commande $commande): bool
    {
        return Paiement::where('service', Help::$COMMANDE)
            ->where('service_id', $commande->id)
            ->where('statut', 1)
            ->whereNull('agence_id')
            ->whereNull('caissier_id')
            ->exists();
    }

    /** La commande attend le retour de la passerelle : rien ne part encore. */
    public static function attendUnPaiementEnLigne(Commande $commande): bool
    {
        return (string) $commande->etat_commande === (string) Help::$COMMANDE_EN_ATTENTE_PAIEMENT;
    }

    public static function type(Commande $commande): string
    {
        return self::estPayeeEnLigne($commande) ? self::FACTURE : self::PROFORMA;
    }

    /** Le titre imprimé en tête du document. */
    public static function titre(Commande $commande): string
    {
        return self::type($commande) === self::FACTURE ? 'Facture de vente' : 'Proforma';
    }

    public static function nomFichier(Commande $commande): string
    {
        return strtolower(self::type($commande)) . '-' . $commande->numero . '.pdf';
    }

    /** Les données du gabarit client.commandeValidee, écran et PDF confondus. */
    public static function donnees(Commande $commande, bool $pourPdf = false): array
    {
        $prixPerso = [];
        if ($commande->client && $commande->client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($commande->client);
        }

        return [
            'commande'     => $commande,
            'image'        => config('constantes.logo'),
            'config'       => Configuration::first(),
            'prixPerso'    => $prixPerso,
            'typeDocument' => self::titre($commande),
            'pourPdf'      => $pourPdf,
        ];
    }

    public static function pdf(Commande $commande)
    {
        return PDF::loadView('client.commandeValidee', self::donnees($commande, true))
            ->setOptions(['isHTML5ParseEnabled' => true, 'defaultPaperOrientation' => 'portrait']);
    }

    private static function colonneEnvoi(string $type): string
    {
        return $type === self::FACTURE ? 'facture_envoyee_le' : 'proforma_envoyee_le';
    }

    /**
     * Envoie le document au client, une seule fois, après la réponse HTTP.
     * Vrai quand un envoi est programmé (ou fait, si $immediat).
     */
    public static function envoyerParCourriel(Commande $commande, bool $immediat = false, bool $forcer = false): bool
    {
        try {
            $commande = Commande::find($commande->id) ?: $commande;
            if (self::attendUnPaiementEnLigne($commande)) {
                return false;
            }

            $type    = self::type($commande);
            $colonne = self::colonneEnvoi($type);
            $memoire = Schema::hasColumn('commande', $colonne);
            if ($memoire && $commande->{$colonne} && !$forcer) {
                return false;
            }

            $client = $commande->client;
            $email  = $client?->user?->email ?: ($client?->email ?? null);
            if (!$email) {
                return false;
            }

            // La date est posée AVANT l'envoi (un second chemin concurrent se
            // tait), effacée si l'envoi échoue.
            if ($memoire) {
                Commande::where('id', $commande->id)->update([$colonne => now()]);
            }

            $envoi = function () use ($commande, $client, $email, $type, $colonne, $memoire) {
                try {
                    Mail::send(new DocumentPdfMail(
                        (string) ($client?->display_name ?? 'Client'),
                        $email,
                        $type,
                        (string) $commande->numero,
                        self::pdf($commande)->output(),
                        self::nomFichier($commande)
                    ));
                } catch (\Throwable $e) {
                    Log::warning($type . ' de la commande ' . $commande->numero . ' non envoyée : ' . $e->getMessage());
                    if ($memoire) {
                        Commande::where('id', $commande->id)->update([$colonne => null]);
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
            Log::warning('Document de la commande ' . ($commande->numero ?? '?') . ' : ' . $e->getMessage());

            return false;
        }
    }
}
