<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ENVOI DU CODE DE CONFIRMATION PAR WHATSAPP.
 *
 * Le code d'inscription ne partait que par courriel. Beaucoup de clients n'ont
 * pas d'adresse consultée régulièrement, quand le message part en indésirables,
 * ou quand le serveur d'envoi est lent — et un code jamais reçu, c'est un compte
 * jamais activé, donc une vente perdue.
 *
 * WhatsApp s'ajoute au courriel, il ne le remplace pas : les deux partent, le
 * client utilise celui qui lui arrive en premier.
 *
 * DEUX PRINCIPES TIENNENT CETTE CLASSE :
 *
 *   · ELLE NE LÈVE JAMAIS. Un fournisseur indisponible ne doit pas faire échouer
 *     une inscription déjà enregistrée. Elle rend `false` et journalise.
 *
 *   · ELLE NE FAIT RIEN TANT QU'ELLE N'EST PAS CONFIGURÉE. Sans jeton ni numéro
 *     d'expédition, elle rend `false` sans appeler personne : poser cette version
 *     ne change donc rien au fonctionnement actuel.
 */
class WhatsAppService
{
    /** L'envoi est-il possible ? Configuré ET activé. */
    public static function estActif(): bool
    {
        return (bool) config('whatsapp.actif')
            && !empty(config('whatsapp.jeton'))
            && !empty(config('whatsapp.numero_id'));
    }

    /**
     * LE NUMÉRO AU FORMAT ATTENDU PAR WHATSAPP.
     *
     * Les clients saisissent « 07 00 00 00 00 » ou « +225 0700000000 ». WhatsApp
     * veut « 2250700000000 » : indicatif collé, sans « + », sans espace, sans
     * zéro de tête international.
     *
     * Rend null si rien d'exploitable ne reste — un numéro mal formé enverrait
     * le code à un inconnu, ou à personne.
     */
    public static function normaliserNumero(?string $numero, ?string $indicatif = null): ?string
    {
        if ($numero === null) {
            return null;
        }

        $indicatif = $indicatif ?: (string) config('whatsapp.indicatif_defaut', '225');

        // On ne garde que les chiffres : espaces, points, tirets et parenthèses
        // sont de la mise en forme, et le « + » se déduit du reste.
        $chiffres = preg_replace('/\D+/', '', $numero) ?? '';

        if ($chiffres === '') {
            return null;
        }

        // « 00225... » : la forme internationale à l'ancienne.
        if (str_starts_with($chiffres, '00')) {
            $chiffres = substr($chiffres, 2);
        }

        // Déjà préfixé par l'indicatif : on n'y touche pas.
        if (str_starts_with($chiffres, $indicatif)) {
            return strlen($chiffres) >= 10 ? $chiffres : null;
        }

        // Un numéro local ivoirien fait dix chiffres depuis 2021. En dessous de
        // huit, ce n'est pas un numéro : mieux vaut ne rien envoyer que d'écrire
        // à quelqu'un d'autre.
        if (strlen($chiffres) < 8) {
            return null;
        }

        return $indicatif . $chiffres;
    }

    /**
     * ENVOIE LE CODE. Rend true si le fournisseur l'a accepté.
     *
     * Le message suit un MODÈLE approuvé par Meta : un texte libre serait refusé
     * pour un destinataire qui n'a jamais écrit à l'entreprise. Le code est
     * passé en paramètre du modèle, et repris dans le bouton de copie que Meta
     * impose pour ce type de message.
     */
    public static function envoyerCode(?string $numero, string $code): bool
    {
        if (!self::estActif()) {
            return false;
        }

        $destinataire = self::normaliserNumero($numero);

        if ($destinataire === null) {
            Log::info('WhatsApp : numéro inexploitable, code non envoyé.', ['saisi' => $numero]);
            return false;
        }

        $url = rtrim((string) config('whatsapp.url_base'), '/')
            . '/' . config('whatsapp.numero_id') . '/messages';

        try {
            $reponse = Http::withToken(config('whatsapp.jeton'))
                ->timeout((int) config('whatsapp.delai', 8))
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $destinataire,
                    'type' => 'template',
                    'template' => [
                        'name' => config('whatsapp.modele'),
                        'language' => ['code' => config('whatsapp.langue', 'fr')],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $code],
                                ],
                            ],
                            // Meta impose un bouton de copie sur les modèles de
                            // type « code d'authentification ». Son paramètre
                            // reprend le code ; l'omettre fait refuser l'envoi.
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $code],
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($reponse->successful()) {
                return true;
            }

            Log::warning('WhatsApp : envoi refusé par le fournisseur.', [
                'statut'   => $reponse->status(),
                'reponse'  => $reponse->body(),
                'numero'   => $destinataire,
            ]);

            return false;
        } catch (\Throwable $e) {
            // Un fournisseur injoignable ne doit pas faire échouer une
            // inscription déjà enregistrée : le courriel, lui, est parti.
            Log::warning('WhatsApp : envoi impossible — ' . $e->getMessage(), [
                'numero' => $destinataire,
            ]);

            return false;
        }
    }
}
