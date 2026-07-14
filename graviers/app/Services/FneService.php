<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Facture;
use App\Models\Enlevement;
use App\Models\Location;
use App\Models\ModePaiement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class FneService
{
    /**
     * Génère un numéro FNE (provisoire) au format : {NCC}U{année}{séquence_10_chiffres}
     *
     * Ce numéro est utilisé en local tant que la plateforme FNE n'a pas
     * renvoyé sa propre référence officielle. Une fois la certification
     * réussie, on conserve ce numéro local mais c'est `fne_reference` qui
     * fait foi pour la DGI.
     */
    public static function genererNumeroFne(): string
    {
        $config = Configuration::first();
        $ncc = $config->ncc ?? '0000000';
        $annee = date('y');

        $prefixe = $ncc . 'U' . $annee;
        $derniereFne = Facture::where('numero_fne', 'like', $prefixe . '%')
            ->orderBy('numero_fne', 'desc')
            ->value('numero_fne');

        if ($derniereFne) {
            $sequence = (int) substr($derniereFne, strlen($prefixe));
            $sequence++;
        } else {
            $sequence = 1;
        }

        return $prefixe . str_pad($sequence, 10, '0', STR_PAD_LEFT);
    }

    /* ====================================================================
     * INTÉGRATION API DGI / FNE
     * ====================================================================
     *
     * Documentation : "PROCEDURE D'INTERFACAGE DES ENTREPRISES PAR API"
     * (Direction Générale des Impôts de Côte d'Ivoire, mai 2025)
     *
     * URL test : http://54.247.95.108/ws
     * URL prod : transmise par la DGI après validation des spécimens
     *
     * Endpoints :
     *   POST $url/external/invoices/sign         (vente / bordereau d'achat)
     *   POST $url/external/invoices/{id}/refund  (facture d'avoir)
     *
     * Authentification : header "Authorization: Bearer <API_KEY>"
     */

    /**
     * La certification FNE est-elle activable (config + clé API présente) ?
     */
    public static function isEnabled(): bool
    {
        return (bool) config('fne.enabled', false)
            && !empty(config('fne.api_key'));
    }

    /**
     * Certifie une facture de vente auprès de la plateforme FNE.
     *
     * En cas de succès, met à jour la facture avec la référence officielle
     * (`fne_reference`), l'URL de vérification (`fne_token`), le statut, etc.
     *
     * En cas d'échec ou de FNE désactivé, la facture est marquée
     * `fne_status = failed|disabled` mais reste exploitable en local.
     *
     * @return array{success:bool, message:string, response:?array}
     */
    public static function signInvoice(Facture $facture, ?array $enlevements = null): array
    {
        // Module désactivé (pas de credentials DGI fournis pour le moment) :
        // on note l'état et on ne tente pas d'appel HTTP.
        if (!self::isEnabled()) {
            $facture->update([
                'fne_status' => 'disabled',
                'fne_error_message' => 'Certification FNE désactivée (clé API non configurée).',
            ]);
            return [
                'success' => false,
                'message' => 'Certification FNE désactivée (clé API non configurée). La facture est créée localement.',
                'response' => null,
            ];
        }

        try {
            // Facture de location : payload construit depuis les lignes de location ;
            // facture de vente : payload construit depuis les enlèvements.
            $payload = (strtoupper((string) $facture->service) === 'LOCATION')
                ? self::buildLocationPayload($facture)
                : self::buildSalePayload($facture, $enlevements);

            $facture->update([
                'fne_request_payload' => $payload,
            ]);

            $url = rtrim(config('fne.base_url'), '/') . '/external/invoices/sign';

            $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . config('fne.api_key'),
                ])
                ->timeout(config('fne.timeout', 20))
                ->retry(
                    config('fne.retry_times', 1),
                    config('fne.retry_sleep', 500),
                    fn ($exception) => $exception instanceof ConnectionException
                )
                ->post($url, $payload);

            $body = $response->json() ?? [];

            if ($response->successful() && !empty($body['reference'])) {
                $facture->update([
                    'fne_invoice_id' => $body['invoice']['id'] ?? null,
                    'fne_reference' => $body['reference'],
                    'fne_token' => $body['token'] ?? null,
                    'fne_warning' => (bool) ($body['warning'] ?? false),
                    'fne_balance_sticker' => $body['balance_sticker'] ?? null,
                    'fne_status' => 'certified',
                    'fne_certified_at' => now(),
                    'fne_error_message' => null,
                    'fne_response_payload' => $body,
                    // On synchronise aussi le numero_fne avec la référence officielle
                    'numero_fne' => $body['reference'],
                ]);

                Log::channel(config('fne.log_channel', 'stack'))
                    ->info('FNE: facture certifiée', [
                        'facture_id' => $facture->id,
                        'reference' => $body['reference'],
                        'balance_sticker' => $body['balance_sticker'] ?? null,
                    ]);

                return [
                    'success' => true,
                    'message' => 'Facture certifiée par la DGI (FNE).',
                    'response' => $body,
                ];
            }

            // Réponse 4xx / 5xx
            $errMessage = $body['message'] ?? ('Erreur HTTP ' . $response->status());
            $facture->update([
                'fne_status' => 'failed',
                'fne_error_message' => $errMessage,
                'fne_response_payload' => $body,
            ]);

            Log::channel(config('fne.log_channel', 'stack'))
                ->error('FNE: échec certification', [
                    'facture_id' => $facture->id,
                    'http_status' => $response->status(),
                    'body' => $body,
                ]);

            return [
                'success' => false,
                'message' => 'Échec de la certification FNE : ' . $errMessage,
                'response' => $body,
            ];

        } catch (Throwable $e) {
            $facture->update([
                'fne_status' => 'failed',
                'fne_error_message' => $e->getMessage(),
            ]);

            Log::channel(config('fne.log_channel', 'stack'))
                ->error('FNE: exception lors de la certification', [
                    'facture_id' => $facture->id,
                    'exception' => $e->getMessage(),
                ]);

            return [
                'success' => false,
                'message' => 'Plateforme FNE injoignable : ' . $e->getMessage(),
                'response' => null,
            ];
        }
    }

    /**
     * Émet une facture d'avoir (refund) sur une facture précédemment certifiée.
     *
     * @param Facture $original Facture certifiée à rembourser.
     * @param array<int, array{id:string, quantity:int|float}> $items Articles à rembourser.
     */
    public static function refundInvoice(Facture $original, array $items): array
    {
        if (!self::isEnabled()) {
            return [
                'success' => false,
                'message' => 'Certification FNE désactivée.',
                'response' => null,
            ];
        }

        if (empty($original->fne_invoice_id)) {
            return [
                'success' => false,
                'message' => "La facture d'origine n'a pas d'identifiant FNE (non certifiée).",
                'response' => null,
            ];
        }

        try {
            $url = rtrim(config('fne.base_url'), '/')
                . '/external/invoices/' . $original->fne_invoice_id . '/refund';

            $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . config('fne.api_key'),
                ])
                ->timeout(config('fne.timeout', 20))
                ->post($url, ['items' => $items]);

            $body = $response->json() ?? [];

            if ($response->successful() && !empty($body['reference'])) {
                Log::channel(config('fne.log_channel', 'stack'))
                    ->info('FNE: facture d\'avoir certifiée', [
                        'facture_origine_id' => $original->id,
                        'reference_avoir' => $body['reference'],
                    ]);

                return [
                    'success' => true,
                    'message' => "Facture d'avoir certifiée par la DGI.",
                    'response' => $body,
                ];
            }

            $errMessage = $body['message'] ?? ('Erreur HTTP ' . $response->status());

            Log::channel(config('fne.log_channel', 'stack'))
                ->error('FNE: échec facture d\'avoir', [
                    'facture_origine_id' => $original->id,
                    'http_status' => $response->status(),
                    'body' => $body,
                ]);

            return [
                'success' => false,
                'message' => "Échec de la facture d'avoir : " . $errMessage,
                'response' => $body,
            ];

        } catch (Throwable $e) {
            Log::channel(config('fne.log_channel', 'stack'))
                ->error('FNE: exception facture d\'avoir', [
                    'facture_origine_id' => $original->id,
                    'exception' => $e->getMessage(),
                ]);

            return [
                'success' => false,
                'message' => "Plateforme FNE injoignable : " . $e->getMessage(),
                'response' => null,
            ];
        }
    }

    /**
     * Construit le corps JSON de la requête `POST /external/invoices/sign`
     * à partir d'une facture GRAVIERS, de son client et des enlèvements liés.
     */
    public static function buildSalePayload(Facture $facture, ?array $enlevementIds = null): array
    {
        $config = Configuration::first();
        $commande = $facture->commande;
        $client = $commande?->client;

        // Détermination du template selon le profil client.
        // Documentation FNE :
        //  - B2C : particulier
        //  - B2B : professionnel possédant un NCC
        //  - B2G : institution gouvernementale
        //  - B2F : client à l'international
        $template = self::determineTemplate($client);

        // Conversion du mode de paiement local vers les valeurs FNE :
        // cash, card, check, mobile-money, transfer, deferred
        $paymentMethod = self::mapPaymentMethod(
            $commande?->modePaiement?->libelle
                ?? $commande?->mode_paiement
                ?? config('fne.defaults.payment_method')
        );

        // Récupération des enlèvements liés à la facture.
        $enlevements = $enlevementIds
            ? Enlevement::whereIn('id', $enlevementIds)->get()
            : Enlevement::where('facture_id', $facture->id)->get();

        $tax = config('fne.defaults.tax', 'TVA');
        $items = [];

        foreach ($enlevements as $env) {
            $produit = $env->produit;
            $detail  = $env->livraison?->detailCommande;
            $prix    = $detail?->prix ?? 0;

            $items[] = [
                'taxes' => [$tax],
                'reference' => $produit?->reference ?? ('PROD-' . ($produit?->id ?? '')),
                'description' => $produit?->nom ?? 'Article',
                'quantity' => (float) $env->qte,
                'amount' => (float) $prix,
                'discount' => 0,
                'measurementUnit' => $produit?->uniteProduit?->libelle ?? 'U',
            ];
        }

        // Coordonnées client (selon template).
        $clientCompanyName = $template === 'B2C'
            ? trim(($client?->nom ?? '') . ' ' . ($client?->prenom ?? ''))
            : ($client?->raison_sociale ?? trim(($client?->nom ?? '') . ' ' . ($client?->prenom ?? '')));

        $payload = [
            'invoiceType' => 'sale',
            'paymentMethod' => $paymentMethod,
            'template' => $template,
            'isRne' => false,
            'clientCompanyName' => $clientCompanyName ?: 'Client',
            'clientPhone' => (string) ($client?->contact1 ?? ''),
            'clientEmail' => (string) ($client?->email ?? ''),
            'clientSellerName' => $facture->user?->nom_prenoms ?? '',
            'pointOfSale' => config('fne.defaults.point_of_sale'),
            'establishment' => $config?->nom_etablissement ?: config('fne.defaults.establishment'),
            'commercialMessage' => config('fne.defaults.commercial_message') ?: '',
            'footer' => config('fne.defaults.footer') ?: '',
            'foreignCurrency' => '',
            'foreignCurrencyRate' => 0,
            'items' => $items,
            'discount' => (float) ($commande?->remise ?? 0),
        ];

        // NCC obligatoire pour le template B2B.
        if ($template === 'B2B' && !empty($client?->ncc_clt)) {
            $payload['clientNcc'] = (string) $client->ncc_clt;
        }

        return $payload;
    }

    /**
     * Construit le corps JSON de `POST /external/invoices/sign` pour une facture
     * de LOCATION. Même structure que buildSalePayload, mais les lignes viennent
     * de detail_location (produit loué, quantité, nombre de jours) au lieu des
     * enlèvements. detail_location.prix stocke le total ligne (qte × pu × jours) :
     * on ramène au montant unitaire pour que quantity × amount = total ligne.
     */
    public static function buildLocationPayload(Facture $facture): array
    {
        $config = Configuration::first();
        $location = $facture->location;
        $client = $location?->client;

        $template = self::determineTemplate($client);
        $paymentMethod = self::mapPaymentMethod(
            optional(ModePaiement::find($location?->mode_paiement_id))->libelle
                ?? config('fne.defaults.payment_method')
        );

        $tax = config('fne.defaults.tax', 'TVA');
        $items = [];

        foreach (($location?->detailLocation ?? []) as $d) {
            $produit    = $d->produit;
            $qte        = (float) ($d->qte ?: 1);
            $jours      = (int) ($d->nombre_jour ?: 1);
            $totalLigne = (float) ($d->prix ?? 0);
            // quantity × amount doit égaler le total de la ligne.
            $amountUnitaire = $qte > 0 ? $totalLigne / $qte : $totalLigne;

            $items[] = [
                'taxes' => [$tax],
                'reference' => $produit?->reference ?? ('LOC-' . ($produit?->id ?? '')),
                'description' => ($produit?->nom ?? 'Location') . ' (location ' . $jours . ' j)',
                'quantity' => $qte,
                'amount' => $amountUnitaire,
                'discount' => 0,
                'measurementUnit' => $produit?->uniteProduit?->libelle ?? 'U',
            ];
        }

        $clientCompanyName = $template === 'B2C'
            ? trim(($client?->nom ?? '') . ' ' . ($client?->prenom ?? ''))
            : ($client?->raison_sociale ?? trim(($client?->nom ?? '') . ' ' . ($client?->prenom ?? '')));

        $payload = [
            'invoiceType' => 'sale',
            'paymentMethod' => $paymentMethod,
            'template' => $template,
            'isRne' => false,
            'clientCompanyName' => $clientCompanyName ?: 'Client',
            'clientPhone' => (string) ($client?->contact1 ?? ''),
            'clientEmail' => (string) ($client?->email ?? ''),
            'clientSellerName' => $facture->user?->nom_prenoms ?? '',
            'pointOfSale' => config('fne.defaults.point_of_sale'),
            'establishment' => $config?->nom_etablissement ?: config('fne.defaults.establishment'),
            'commercialMessage' => config('fne.defaults.commercial_message') ?: '',
            'footer' => config('fne.defaults.footer') ?: '',
            'foreignCurrency' => '',
            'foreignCurrencyRate' => 0,
            'items' => $items,
            'discount' => (float) ($location?->remise ?? 0),
        ];

        if ($template === 'B2B' && !empty($client?->ncc_clt)) {
            $payload['clientNcc'] = (string) $client->ncc_clt;
        }

        return $payload;
    }

    /**
     * Détermine le template FNE en fonction du profil client.
     */
    public static function determineTemplate($client): string
    {
        if (!$client) {
            return config('fne.defaults.template', 'B2C');
        }

        // Présence d'un NCC -> client professionnel
        if (!empty($client->ncc_clt)) {
            return 'B2B';
        }

        // Heuristique : type_client peut indiquer "particulier" ou "entreprise"
        $type = strtolower((string) ($client->type_client ?? ''));
        if (in_array($type, ['particulier', 'individuel', 'b2c'], true)) {
            return 'B2C';
        }
        if (in_array($type, ['etat', 'gouvernement', 'b2g', 'institution'], true)) {
            return 'B2G';
        }
        if (in_array($type, ['international', 'b2f', 'etranger'], true)) {
            return 'B2F';
        }

        return config('fne.defaults.template', 'B2C');
    }

    /**
     * Convertit un libellé local de mode de paiement en valeur FNE.
     */
    public static function mapPaymentMethod(?string $local): string
    {
        $key = strtolower(trim((string) $local));

        return match (true) {
            str_contains($key, 'espèce') || str_contains($key, 'espece') || str_contains($key, 'cash') => 'cash',
            str_contains($key, 'carte') || str_contains($key, 'card') || str_contains($key, 'cb') => 'card',
            str_contains($key, 'chèque') || str_contains($key, 'cheque') || str_contains($key, 'check') => 'check',
            str_contains($key, 'mobile') || str_contains($key, 'momo') || str_contains($key, 'orange money') || str_contains($key, 'wave') || str_contains($key, 'mtn') => 'mobile-money',
            str_contains($key, 'virement') || str_contains($key, 'transfer') => 'transfer',
            str_contains($key, 'terme') || str_contains($key, 'crédit') || str_contains($key, 'credit') || str_contains($key, 'deferred') => 'deferred',
            default => config('fne.defaults.payment_method', 'cash'),
        };
    }

    /**
     * Génère le contenu du QR Code FNE pour une facture.
     *
     * Si la facture a été certifiée par la DGI, on utilise l'URL officielle
     * de vérification renvoyée par la plateforme (`fne_token`). Sinon, on
     * retombe sur un QR local (en attendant les credentials FNE).
     */
    public static function genererQrCodeData(Facture $facture): string
    {
        if (!empty($facture->fne_token)) {
            return $facture->fne_token;
        }

        $config = Configuration::first();

        return implode("|", [
            "NCC:" . ($config->ncc ?? ''),
            "F:" . ($facture->fne_reference ?? $facture->numero_fne ?? $facture->numero),
            "D:" . $facture->created_at->format('d/m/Y H:i'),
            "TTC:" . number_format($facture->montant, 0, '', '') . "FCFA",
        ]);
    }

    /**
     * Génère un QR Code en PNG base64
     * Utilise api.qrserver.com avec cache local pour performance
     */
    public static function genererQrCodeBase64(string $data): string
    {
        if (empty($data)) return '';

        // Cache le QR code pendant 24h pour éviter les appels répétés
        $cacheKey = 'qrcode_' . md5($data);
        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        $encoded = urlencode($data);

        // Méthode 1: api.qrserver.com (fiable et gratuit)
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get("https://api.qrserver.com/v1/create-qr-code/", [
                    'size' => '200x200',
                    'data' => $data,
                    'format' => 'png',
                ]);

            if ($response->successful()) {
                $result = 'data:image/png;base64,' . base64_encode($response->body());
                Cache::put($cacheKey, $result, 86400); // 24h
                return $result;
            }
        } catch (\Exception $e) {
            // Fallback
        }

        // Méthode 2: quickchart.io (alternative)
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get("https://quickchart.io/qr", [
                    'text' => $data,
                    'size' => 200,
                    'format' => 'png',
                ]);

            if ($response->successful()) {
                $result = 'data:image/png;base64,' . base64_encode($response->body());
                Cache::put($cacheKey, $result, 86400);
                return $result;
            }
        } catch (\Exception $e) {
            // Fallback
        }

        // Méthode 3: Google Charts (peut être déprécié mais tenter quand même)
        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->get("https://chart.googleapis.com/chart", [
                    'cht' => 'qr',
                    'chs' => '200x200',
                    'chl' => $data,
                    'choe' => 'UTF-8',
                ]);

            if ($response->successful()) {
                $result = 'data:image/png;base64,' . base64_encode($response->body());
                Cache::put($cacheKey, $result, 86400);
                return $result;
            }
        } catch (\Exception $e) {
            // Aucune API accessible
        }

        return '';
    }

    /**
     * Récupère toutes les données FNE nécessaires pour le template
     */
    public static function getDonneesFne(?Facture $facture = null, $client = null): array
    {
        $config = Configuration::first();

        $data = [
            'fne_config' => [
                'raison_sociale'    => $config->raison_sociale ?? 'DALAKOUN',
                'ncc'               => $config->ncc ?? '',
                'regime_imposition' => $config->regime_imposition ?? '',
                'centre_impots'     => $config->centre_impots ?? '',
                'rccm'              => $config->rccm ?? '',
                'ref_bancaires'     => $config->ref_bancaires ?? '',
                'cnps'              => $config->cnps ?? '',
                'capital_social'    => $config->capital_social ?? '',
                'adresse_siege'     => $config->adresse_siege ?? '',
                'telephone'         => $config->telephone ?? '',
                'email_entreprise'  => $config->email_entreprise ?? '',
                'nom_etablissement' => $config->nom_etablissement ?? '',
                'nom_pdv'           => $config->nom_pdv ?? '',
                'tva'               => $config->tva ?? 0,
                'devise'            => $config->devise ?? 'FCFA',
            ],
            'fne_client' => [
                'nom'               => '',
                'adresse'           => '',
                'ncc'               => '',
                'regime_imposition' => '',
            ],
            'fne_numero'    => '',
            'fne_qr_code'   => '',
            'fne_date'      => now()->format('d/m/Y H:i:s'),
            'fne_certified' => false,
            'fne_reference' => '',
            'fne_token_url' => '',
        ];

        // Données client
        if ($client) {
            $data['fne_client'] = [
                'nom'               => trim(($client->nom ?? '') . ' ' . ($client->prenom ?? '')),
                'adresse'           => $client->user->email ?? '',
                'ncc'               => $client->ncc_clt ?? '',
                'regime_imposition' => $client->regime_imposition ?? '',
            ];
        }

        // Données facture
        if ($facture && $facture->id) {
            // Si la facture a été certifiée par la DGI, on affiche la référence officielle
            $data['fne_numero'] = $facture->fne_reference ?? $facture->numero_fne ?? $facture->numero;
            $data['fne_date'] = $facture->created_at ? $facture->created_at->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s');
            $data['fne_certified'] = $facture->isCertifiedFne();
            $data['fne_reference'] = $facture->fne_reference ?? '';
            $data['fne_token_url'] = $facture->fne_token ?? '';

            $qrData = self::genererQrCodeData($facture);
            $data['fne_qr_code'] = self::genererQrCodeBase64($qrData);
        }

        return $data;
    }

    /**
     * Récupère les données FNE pour un devis
     */
    public static function getDonneesFneDevis($devis, $client = null): array
    {
        $config = Configuration::first();

        $data = self::getDonneesFne(null, $client);
        $data['fne_numero'] = $devis->numero ?? '';
        $data['fne_date'] = $devis->created_at ? $devis->created_at->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s');

        // QR pour devis
        $qrData = implode("|", [
            "NCC:" . ($config->ncc ?? ''),
            "DEV:" . ($devis->numero ?? ''),
            "D:" . $data['fne_date'],
            "TTC:" . number_format(($devis->montant ?? 0) + ($devis->tva ?? 0) + ($devis->cout_livraison ?? 0) - ($devis->cout_reduction ?? 0), 0, '', '') . "FCFA",
        ]);
        $data['fne_qr_code'] = self::genererQrCodeBase64($qrData);

        return $data;
    }
}
