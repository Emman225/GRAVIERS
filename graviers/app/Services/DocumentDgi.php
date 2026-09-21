<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Facture;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PDF;

/**
 * LE DOCUMENT TEL QUE LA DGI LE MONTRE (lot 96, 16/09/2026).
 *
 * Le bouton « Exporter » de la page de vérification FNE ne demande rien au
 * serveur : le PDF est dessiné dans le navigateur à partir des données
 * publiques de GET {plateforme}/ws/invoices/qr/{jeton}. Le site lit ces mêmes
 * données — une fois, à la certification, puis les garde sur la facture
 * (fne_verification_payload) — et dessine le document identique : en-tête de
 * l'entreprise telle que la DGI la connaît, lignes, totaux et résumé tels que
 * la DGI les a calculés. Ce document sert au courriel, au compte client (web
 * et mobile) et à l'admin. Sans données (DGI injoignable, facture non
 * certifiée), l'ancien document local reste le repli.
 *
 * La réponse de la DGI contient la CLÉ API de l'entreprise (company.apiKey) :
 * elle n'est JAMAIS mémorisée — seuls les champs utiles au document le sont.
 */
class DocumentDgi
{
    public static string $derniereErreur = '';

    /** L'adresse JSON de la page de vérification, déduite du lien de vérification. */
    public static function urlDonnees(Facture $facture): ?string
    {
        $lien = (string) ($facture->fne_token ?? '');
        if ($lien === '') {
            return null;
        }
        $parties = parse_url($lien);
        if (empty($parties['host']) || empty($parties['path'])) {
            return null;
        }
        $jeton = basename($parties['path']);
        if ($jeton === '' || !preg_match('/^[0-9a-f-]{20,}$/i', $jeton)) {
            return null;
        }
        $origine = ($parties['scheme'] ?? 'http') . '://' . $parties['host'] . (isset($parties['port']) ? ':' . $parties['port'] : '');

        return $origine . '/ws/invoices/qr/' . $jeton;
    }

    /** Les données de la DGI : mémorisées, sinon demandées (et mémorisées). Null si indisponibles. */
    public static function donnees(Facture $facture, bool $rafraichir = false): ?array
    {
        self::$derniereErreur = '';
        if (!$facture->isCertifiedFne()) {
            self::$derniereErreur = 'facture non certifiée';
            return null;
        }
        $memoire = Schema::hasColumn('facture', 'fne_verification_payload');
        if (!$rafraichir && $memoire) {
            $connues = $facture->fne_verification_payload;
            // Mémoire écrite en chaîne (double encodage) : on la décode quand même.
            if (is_string($connues)) {
                $connues = json_decode($connues, true);
            }
            if (is_array($connues) && !empty($connues['reference'])) {
                return $connues;
            }
        }
        $url = self::urlDonnees($facture);
        if (!$url) {
            self::$derniereErreur = 'lien de vérification absent';
            return null;
        }
        try {
            $reponse = Http::timeout((int) config('fne.timeout', 20))->acceptJson()->get($url);
            $brut = $reponse->json();
            if (!$reponse->successful() || !is_array($brut) || empty($brut['reference'])) {
                self::$derniereErreur = 'la plateforme a répondu HTTP ' . $reponse->status();
                Log::warning('Données DGI de la facture ' . $facture->id . ' non obtenues : ' . self::$derniereErreur);
                return null;
            }
            $utiles = self::filtrer($brut);
            if ($memoire) {
                Facture::where('id', $facture->id)->update(['fne_verification_payload' => json_encode($utiles, JSON_UNESCAPED_UNICODE)]);
            }

            return $utiles;
        } catch (\Throwable $e) {
            self::$derniereErreur = 'plateforme injoignable (' . $e->getMessage() . ')';
            Log::warning('Plateforme DGI injoignable pour la facture ' . $facture->id . ' : ' . $e->getMessage());

            return null;
        }
    }

    /** Ne garde que ce que le document imprime — jamais la clé API ni les compteurs du compte. */
    public static function filtrer(array $brut): array
    {
        $champs = ['id', 'token', 'reference', 'parentReference', 'type', 'subtype', 'date', 'paymentMethod', 'amount', 'vatAmount',
            'fiscalStamp', 'discount', 'totalBeforeTaxes', 'totalDiscounted', 'totalTaxes', 'totalAfterTaxes', 'totalCustomTaxes',
            'totalDue', 'clientNcc', 'clientCompanyName', 'clientPhone', 'clientEmail', 'clientSellerName', 'clientEstablishment',
            'clientPointOfSale', 'clientTaxRegime', 'template', 'footer', 'commercialMessage', 'foreignCurrency', 'foreignCurrencyRate'];
        $u = [];
        foreach ($champs as $c) {
            if (array_key_exists($c, $brut)) {
                $u[$c] = $brut[$c];
            }
        }
        $u['items'] = [];
        foreach ((array) ($brut['items'] ?? []) as $it) {
            $u['items'][] = [
                'reference'       => $it['reference'] ?? '',
                'description'     => $it['description'] ?? '',
                'quantity'        => (float) ($it['quantity'] ?? 0),
                'amount'          => (float) ($it['amount'] ?? 0),
                'discount'        => (float) ($it['discount'] ?? 0),
                'measurementUnit' => $it['measurementUnit'] ?? '',
                'taxes'           => array_values(array_map(fn ($t) => ['amount' => (float) ($t['amount'] ?? 0), 'name' => $t['name'] ?? '', 'shortName' => $t['shortName'] ?? ''], (array) ($it['taxes'] ?? []))),
                'customTaxes'     => array_values(array_map(fn ($t) => ['amount' => (float) ($t['amount'] ?? 0), 'name' => $t['name'] ?? ''], (array) ($it['customTaxes'] ?? []))),
            ];
        }
        $u['customTaxes'] = array_values(array_map(fn ($t) => ['amount' => (float) ($t['amount'] ?? 0), 'name' => $t['name'] ?? ''], (array) ($brut['customTaxes'] ?? [])));
        $c = (array) ($brut['company'] ?? []);
        $u['company'] = [
            'name'          => $c['name'] ?? '',
            'ncc'           => $c['ncc'] ?? '',
            'taxRegime'     => $c['taxRegime'] ?? '',
            'rccm'          => $c['rccm'] ?? '',
            'address'       => $c['address'] ?? '',
            'phone'         => $c['phone'] ?? '',
            'email'         => $c['email'] ?? '',
            'bankReference' => $c['bankReference'] ?? '',
            'dr'            => $c['dr']['name'] ?? '',
            'centre'        => $c['accountingPosition']['name'] ?? '',
        ];

        return $u;
    }

    /** Les variables du gabarit document/factureDgi. */
    public static function variables(Facture $facture, array $d): array
    {
        $config = Configuration::first();
        $entreprise = (array) ($d['company'] ?? []);
        $base = FneService::getDonneesFne($facture, $facture->client);
        $base['fne_emetteur'] = (string) (($entreprise['name'] ?? '') ?: ($config->raison_sociale ?? 'DALAKOUN'));
        $base['fne_config'] = array_merge($base['fne_config'], [
            'raison_sociale'    => $base['fne_emetteur'],
            'ncc'               => ($entreprise['ncc'] ?? '') ?: ($config->ncc ?? ''),
            'regime_imposition' => ($entreprise['taxRegime'] ?? '') ?: ($config->regime_imposition ?? ''),
            'centre_impots'     => ($entreprise['centre'] ?? '') ?: ($config->centre_impots ?? ''),
            'rccm'              => ($entreprise['rccm'] ?? '') ?: ($config->rccm ?? ''),
            'ref_bancaires'     => (string) ($entreprise['bankReference'] ?? ''),
            'nom_etablissement' => (string) ($d['clientEstablishment'] ?? ($config->nom_etablissement ?? '')),
            'adresse_siege'     => ($entreprise['address'] ?? '') ?: ($config->adresse_siege ?? ''),
            'telephone'         => ($entreprise['phone'] ?? '') ?: ($config->telephone ?? ''),
            'email_entreprise'  => ($entreprise['email'] ?? '') ?: ($config->email_entreprise ?? ''),
            'nom_pdv'           => (string) ($d['clientPointOfSale'] ?? ($config->nom_pdv ?? '')),
        ]);
        // LOT 111 (19/09/2026) : la DGI ne renvoie ni le NCC ni le régime du client quand la
        // facture a été émise sans eux (clientNcc / clientTaxRegime à null) : on complète
        // avec la fiche du client. Même repli pour l'émetteur (NCC, RCCM, régime) : la
        // réponse de la DGI d'abord, puis Paramètres → informations de l'entreprise.
        $client = $facture->client;
        $regimeClient = (string) ($d['clientTaxRegime'] ?? '');
        if ($regimeClient === '' && $client) {
            $regimeClient = (string) (\App\Support\RegimeImposition::code($client->regime_imposition) ?? $client->regime_imposition);
        }
        $base['fne_client'] = [
            'nom'               => (string) ($d['clientCompanyName'] ?? ''),
            'adresse'           => trim(implode(' - ', array_filter([(string) ($d['clientEmail'] ?? ''), (string) ($d['clientPhone'] ?? '')]))),
            'ncc'               => (string) (($d['clientNcc'] ?? '') ?: preg_replace('/\s+/', '', (string) ($client->ncc_clt ?? ''))),
            'regime_imposition' => $regimeClient,
        ];
        $base['fne_config']['regime_imposition'] = (string) (\App\Support\RegimeImposition::code($base['fne_config']['regime_imposition'])
            ?? $base['fne_config']['regime_imposition']);
        $base['fne_numero'] = (string) ($d['reference'] ?? $facture->fne_reference);
        $base['fne_certified'] = true;
        $base['fne_reference'] = (string) ($d['reference'] ?? $facture->fne_reference);
        try {
            $base['fne_date'] = \Carbon\Carbon::parse((string) $d['date'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            $base['fne_date'] = $facture->fne_certified_at?->format('d/m/Y H:i:s') ?? '';
        }
        $base['facture'] = $facture;
        $base['dgi'] = $d;
        $base['typeDocument'] = $facture->estUnAvoir() ? "Facture d'avoir" : CourrielFactureFne::typeDocument($facture);
        $base['modePaiement'] = self::libelleModePaiement((string) ($d['paymentMethod'] ?? ''));

        return $base;
    }

    public static function libelleModePaiement(string $code): string
    {
        return match (strtolower($code)) {
            'cash' => 'Cash',
            'card' => 'Carte bancaire',
            'check' => 'Chèque',
            'mobile-money' => 'Mobile money',
            'transfer' => 'Virement bancaire',
            'deferred' => 'À terme',
            default => $code,
        };
    }

    /** Le PDF du document DGI, ou null quand les données manquent. */
    public static function pdf(Facture $facture)
    {
        $d = self::donnees($facture);
        if (!$d) {
            return null;
        }

        // Pas de setOptions() ici : il remplace les options de DomPDF et lui fait
        // perdre le répertoire des polices, donc Montserrat (les deux options
        // héritées des autres documents étaient de toute façon sans effet).
        $pdf = PDF::loadView('document.factureDgi', self::variables($facture, $d));
        // Les polices de la plateforme FNE (Montserrat Medium / SemiBold, fichiers
        // publics de la DGI copiés dans public/fonts/dgi), enregistrées par le code :
        // c'est ce qui rend le document identique à l'export de la DGI.
        try {
            $metriques = $pdf->getDomPDF()->getFontMetrics();
            $metriques->registerFont(['family' => 'MontserratM', 'style' => 'normal', 'weight' => 'normal'], public_path('fonts/dgi/Montserrat-Medium.ttf'));
            $metriques->registerFont(['family' => 'MontserratSB', 'style' => 'normal', 'weight' => 'normal'], public_path('fonts/dgi/Montserrat-SemiBold.ttf'));
        } catch (\Throwable $e) {
            Log::warning('Polices DGI non enregistrées : ' . $e->getMessage());
        }

        return $pdf;
    }

    public static function nomFichier(Facture $facture): string
    {
        // La référence de la DGI (celle des données mémorisées), sinon celle de la facture.
        $connues = is_array($facture->fne_verification_payload ?? null) ? $facture->fne_verification_payload : [];
        $ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string) (($connues['reference'] ?? '') ?: ($facture->fne_reference ?: $facture->numero)));

        return ($facture->estUnAvoir() ? 'Facture_Avoir_DGI_' : 'Facture_DGI_') . $ref . '.pdf';
    }

    /**
     * La réponse HTTP du document DGI (aperçu ou fichier), ou null quand il
     * faut se rabattre sur le document local : UN SEUL aiguillage pour toutes
     * les entrées (compte client, admin, routes internes).
     */
    public static function reponse(Facture $facture, string $action = 'voir')
    {
        $pdf = $facture->isCertifiedFne() ? self::pdf($facture) : null;
        if (!$pdf) {
            return null;
        }
        $nom = self::nomFichier($facture);

        return $action === 'telecharger' ? $pdf->download($nom) : $pdf->stream($nom);
    }
}
