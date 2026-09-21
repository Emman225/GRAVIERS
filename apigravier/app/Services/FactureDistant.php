<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LES FACTURES DGI DU CLIENT, TELLES QUE LE SITE LES TIENT (lot 95, 16/09/2026) :
 * la liste et le PDF viennent du site (jeton interne), comme le reçu (lot 88).
 */
class FactureDistant
{
    public static string $derniereErreur = '';

    private static function site(): ?array
    {
        self::$derniereErreur = '';
        $jeton = (string) config('constantes.jeton_interne');
        $site  = rtrim((string) config('constantes.url_site'), '/');
        if ($jeton === '' || $site === '') {
            self::$derniereErreur = 'JETON_INTERNE ou URL_SITE absent du .env de l\'API';
            return null;
        }

        return [$site, $jeton];
    }

    public static function liste(Client $client): ?array
    {
        $acces = self::site();
        if (!$acces) {
            return null;
        }
        [$site, $jeton] = $acces;
        try {
            $reponse = Http::timeout(30)->acceptJson()
                ->post($site . '/api/interne/client/' . $client->id . '/factures', ['jeton' => $jeton]);
            if ($reponse->successful() && (int) $reponse->json('code') === 200 && is_array($reponse->json('data'))) {
                return $reponse->json('data');
            }
            self::$derniereErreur = 'le site a répondu HTTP ' . $reponse->status();
        } catch (\Throwable $e) {
            self::$derniereErreur = 'site injoignable (' . $e->getMessage() . ')';
        }
        Log::warning('Factures DGI du client ' . $client->id . ' non obtenues : ' . self::$derniereErreur);

        return null;
    }

    public static function pdf(int $factureId): ?string
    {
        $acces = self::site();
        if (!$acces) {
            return null;
        }
        [$site, $jeton] = $acces;
        try {
            $reponse = Http::timeout(45)->post($site . '/api/interne/facture/' . $factureId . '/pdf', ['jeton' => $jeton]);
            $corps = (string) $reponse->body();
            if ($reponse->successful() && str_starts_with($corps, '%PDF')) {
                return $corps;
            }
            $detail = (string) ($reponse->json('message') ?? '');
            self::$derniereErreur = 'le site a répondu HTTP ' . $reponse->status()
                . ($reponse->status() === 403 ? ' (JETON_INTERNE différent entre le site et l\'API ?)' : '')
                . ($detail !== '' ? ' : ' . $detail : '');
        } catch (\Throwable $e) {
            self::$derniereErreur = 'site injoignable (' . $e->getMessage() . ')';
        }
        Log::warning('PDF de la facture ' . $factureId . ' non obtenu : ' . self::$derniereErreur);

        return null;
    }
}
