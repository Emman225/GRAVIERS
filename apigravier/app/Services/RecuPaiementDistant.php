<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Commande;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\Paiement;
use App\Models\User;
use Help;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * LE REÇU D'UN PAIEMENT FAIT DEPUIS L'APPLICATION.
 *
 * Après un paiement en ligne réussi, le client ne recevait aucun reçu
 * (point 11, 07/09/2026). Cette API ne sait pas produire de PDF : elle
 * demande donc au SITE d'envoyer le reçu — le même document qu'au guichet —
 * en présentant le jeton partagé JETON_INTERNE. Si le site ne répond pas, ou
 * si le jeton n'est pas configuré, le client reçoit tout de même son reçu,
 * en texte dans le corps du courriel : mieux vaut un reçu simple qu'aucun.
 *
 * Un seul envoi par règlement : la date est inscrite sur le paiement
 * (colonne recu_envoye_le, posée par la migration du site).
 */
class RecuPaiementDistant
{
    public static function envoyer(Paiement $paiement): void
    {
        try {
            if (!Schema::hasColumn('paiement', 'recu_envoye_le')) {
                // Base pas encore migrée côté site : on envoie sans mémoire
                // d'envoi plutôt que rien.
                self::envoyerEnTexte($paiement);
                return;
            }

            $paiement = Paiement::find($paiement->id) ?: $paiement;
            if ($paiement->recu_envoye_le) {
                return;
            }

            if (self::demanderAuSite($paiement)) {
                return;
            }

            self::envoyerEnTexte($paiement);
            Paiement::where('id', $paiement->id)->update(['recu_envoye_le' => now()]);
        } catch (\Throwable $e) {
            Log::error('Reçu du paiement ' . $paiement->id . ' : ' . $e->getMessage());
        }
    }

    /**
     * LE PDF DU REÇU, TEL QUE LE SITE LE PRODUIT (lot 88, 15/09/2026) : pour
     * l'application, qui l'affiche à la place de son reçu local. Null quand le
     * site ne répond pas ou que le jeton manque : l'application se rabat alors
     * sur son propre reçu.
     */
    /** Le motif du dernier échec de pdf(), pour le dire à l'application. */
    public static string $derniereErreur = '';

    public static function pdf(Paiement $paiement): ?string
    {
        self::$derniereErreur = '';
        $jeton = (string) config('constantes.jeton_interne');
        $site  = rtrim((string) config('constantes.url_site'), '/');
        if ($jeton === '' || $site === '') {
            self::$derniereErreur = 'JETON_INTERNE ou URL_SITE absent du .env de l\'API';
            return null;
        }
        try {
            $reponse = Http::timeout(30)
                ->post($site . '/api/interne/recu-paiement/' . $paiement->id . '/pdf', ['jeton' => $jeton]);
            $corps = (string) $reponse->body();
            if ($reponse->successful() && str_starts_with($corps, '%PDF')) {
                return $corps;
            }
            $detail = (string) ($reponse->json('message') ?? '');
            self::$derniereErreur = 'le site a répondu HTTP ' . $reponse->status()
                . ($reponse->status() === 403 ? ' (JETON_INTERNE différent entre le site et l\'API ?)' : '')
                . ($detail !== '' ? ' : ' . $detail : '');
            Log::warning('Le site n\'a pas fourni le PDF du reçu ' . $paiement->id . ' : ' . self::$derniereErreur);
        } catch (\Throwable $e) {
            self::$derniereErreur = 'site injoignable (' . $e->getMessage() . ')';
            Log::warning('Site injoignable pour le PDF du reçu ' . $paiement->id . ' : ' . $e->getMessage());
        }

        return null;
    }

    /** Le site envoie le PDF ; il pose lui-même la date d'envoi. */
    private static function demanderAuSite(Paiement $paiement): bool
    {
        $jeton = (string) config('constantes.jeton_interne');
        $site  = rtrim((string) config('constantes.url_site'), '/');
        if ($jeton === '' || $site === '') {
            return false;
        }

        try {
            $reponse = Http::timeout(20)
                ->acceptJson()
                ->post($site . '/api/interne/recu-paiement/' . $paiement->id, ['jeton' => $jeton]);

            if ($reponse->successful() && (int) $reponse->json('code') === 200) {
                return true;
            }
            Log::warning('Le site n\'a pas envoyé le reçu du paiement ' . $paiement->id
                . ' : HTTP ' . $reponse->status() . ' ' . substr($reponse->body(), 0, 200));
        } catch (\Throwable $e) {
            Log::warning('Site injoignable pour le reçu du paiement ' . $paiement->id . ' : ' . $e->getMessage());
        }

        return false;
    }

    /** Repli : le reçu en texte, dans le corps du courriel. */
    private static function envoyerEnTexte(Paiement $paiement): void
    {
        $client = Client::lire($paiement->client_id);
        $user   = $client->user_id ? User::lire($client->user_id) : null;
        $email  = $user?->email ?: ($client->email ?? null);
        if (!$email) {
            return;
        }

        $libelleOperation = 'N° Commande';
        $numeroOperation  = '-';
        $bonCommande      = null;
        if ($paiement->service == Help::$COMMANDE) {
            $numeroOperation = Commande::lire($paiement->service_id)->numero ?? '-';
            // Point 16 (07/09/2026) : le bon de commande du client figure sur
            // le reçu, comme sur la facture.
            $bonCommande = \App\Models\BlClient::where('commande_id', $paiement->service_id)->value('numero') ?: '-';
        } elseif ($paiement->service == Help::$LOCATION) {
            $libelleOperation = 'N° Location';
            $numeroOperation  = Location::lire($paiement->service_id)->numero ?? '-';
        } elseif ($paiement->service == Help::$LIVRAISON) {
            // Une avance s'impute aussi sur une demande de livraison (11/09/2026).
            $libelleOperation = 'N° Demande de livraison';
            $numeroOperation  = \App\Models\DemandeLivraison::find($paiement->service_id)?->numero ?? '-';
        }

        $ligne = LignePaiement::where('paiement_id', $paiement->id)->orderBy('id')->first();

        Mail::to($email)->send(new \App\Mail\RecuPaiementMail([
            'nomClient'        => trim(($client->nom ?? '') . ' ' . ($client->prenom ?? '')) ?: 'Client',
            'numeroRecu'       => $paiement->numero_recu ?: $paiement->code,
            'date'             => $paiement->created_at ? $paiement->created_at->format('d/m/Y H:i') : date('d/m/Y H:i'),
            'libelleOperation' => $libelleOperation,
            'numeroOperation'  => $numeroOperation,
            'bonCommande'      => $bonCommande,
            'mode'             => $ligne?->moyen_paiement ?: 'Paiement en ligne',
            'reference'        => $ligne?->reference ?: '-',
            'montant'          => number_format((float) $paiement->montant_total, 0, '', ' ') . ' FCFA',
        ]));
    }
}
