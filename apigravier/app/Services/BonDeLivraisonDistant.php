<?php

namespace App\Services;

use App\Models\Livraison;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LE BON DE LIVRAISON DU CLIENT ENTREPRISE, APRÈS UNE COURSE CLÔTURÉE DEPUIS
 * L'APPLICATION DES LIVREURS (lot 84, 15/09/2026). Cette API ne produit pas
 * de PDF : elle demande au site d'envoyer le bon (jeton interne), comme pour
 * les reçus et la proforma. Jamais bloquant.
 */
class BonDeLivraisonDistant
{
    public static function envoyer(Livraison $livraison): bool
    {
        try {
            $jeton = (string) config('constantes.jeton_interne');
            $site  = rtrim((string) config('constantes.url_site'), '/');
            if ($jeton === '' || $site === '' || empty($livraison->id)) {
                return false;
            }
            $reponse = Http::timeout(30)->acceptJson()
                ->post($site . '/api/interne/livraison/' . $livraison->id . '/bon-de-livraison', ['jeton' => $jeton]);
            if ($reponse->successful() && (int) $reponse->json('code') === 200) {
                return true;
            }
            Log::warning('Le site n\'a pas envoyé le bon de livraison de la course ' . $livraison->id
                . ' : HTTP ' . $reponse->status() . ' ' . substr($reponse->body(), 0, 200));
        } catch (\Throwable $e) {
            Log::warning('Site injoignable pour le bon de livraison de la course ' . ($livraison->id ?? '?') . ' : ' . $e->getMessage());
        }

        return false;
    }

    public static function envoyerApresLaReponse(Livraison $livraison): void
    {
        $envoi = static function () use ($livraison) {
            self::envoyer($livraison);
        };
        if (app()->runningInConsole()) {
            $envoi();
        } else {
            app()->terminating($envoi);
        }
    }
}
