<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * « MES PAIEMENTS » DU CLIENT, TELS QUE LE SITE LES LISTE (lot 89, 16/09/2026) :
 * l'application affichait ses « factures » (table facture) là où le web montre
 * les règlements. L'API demande la liste au site (jeton interne) ; null si le
 * site ne répond pas — l'ancienne liste sert alors de repli.
 */
class PaiementsDistant
{
    public static string $derniereErreur = '';

    public static function liste(Client $client): ?array
    {
        self::$derniereErreur = '';
        $jeton = (string) config('constantes.jeton_interne');
        $site  = rtrim((string) config('constantes.url_site'), '/');
        if ($jeton === '' || $site === '') {
            self::$derniereErreur = 'JETON_INTERNE ou URL_SITE absent du .env de l\'API';
            return null;
        }
        try {
            $reponse = Http::timeout(30)->acceptJson()
                ->post($site . '/api/interne/client/' . $client->id . '/paiements', ['jeton' => $jeton]);
            if ($reponse->successful() && (int) $reponse->json('code') === 200 && is_array($reponse->json('data'))) {
                return $reponse->json('data');
            }
            self::$derniereErreur = 'le site a répondu HTTP ' . $reponse->status();
            Log::warning('Le site n\'a pas fourni les paiements du client ' . $client->id . ' : ' . self::$derniereErreur);
        } catch (\Throwable $e) {
            self::$derniereErreur = 'site injoignable (' . $e->getMessage() . ')';
            Log::warning('Site injoignable pour les paiements du client ' . $client->id . ' : ' . $e->getMessage());
        }

        return null;
    }
}
