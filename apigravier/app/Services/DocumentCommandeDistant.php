<?php

namespace App\Services;

use App\Models\Commande;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LA PROFORMA (OU LA FACTURE) D'UNE COMMANDE PASSÉE DEPUIS L'APPLICATION
 * (lot 81, 15/09/2026).
 *
 * Règle du client : après la commande, la proforma part au client par
 * courriel ; pour une commande payée en ligne, c'est la facture, au paiement
 * confirmé. Cette API ne sait pas produire de PDF : elle demande au SITE
 * d'envoyer le document — le même que celui de « Mon compte » —, en
 * présentant le jeton partagé JETON_INTERNE (même mécanique que
 * RecuPaiementDistant). Le site décide seul de ce qu'il envoie (proforma ou
 * facture, rien pour une commande qui attend son paiement) et n'envoie
 * chaque document qu'une fois.
 *
 * Jamais bloquant : un site injoignable ne fait échouer ni la commande ni
 * le paiement.
 */
class DocumentCommandeDistant
{
    public static function envoyer(Commande $commande): bool
    {
        try {
            $jeton = (string) config('constantes.jeton_interne');
            $site  = rtrim((string) config('constantes.url_site'), '/');
            if ($jeton === '' || $site === '' || empty($commande->numero)) {
                return false;
            }

            $reponse = Http::timeout(30)
                ->acceptJson()
                ->post($site . '/api/interne/commande/' . $commande->numero . '/document', ['jeton' => $jeton]);

            if ($reponse->successful() && (int) $reponse->json('code') === 200) {
                return true;
            }
            Log::warning('Le site n\'a pas envoyé le document de la commande ' . $commande->numero
                . ' : HTTP ' . $reponse->status() . ' ' . substr($reponse->body(), 0, 200));
        } catch (\Throwable $e) {
            Log::warning('Site injoignable pour le document de la commande ' . ($commande->numero ?? '?') . ' : ' . $e->getMessage());
        }

        return false;
    }

    /** Après la réponse HTTP (tout de suite en console), jamais bloquant. */
    public static function envoyerApresLaReponse(Commande $commande): void
    {
        $envoi = static function () use ($commande) {
            self::envoyer($commande);
        };
        if (app()->runningInConsole()) {
            $envoi();
        } else {
            app()->terminating($envoi);
        }
    }
}
