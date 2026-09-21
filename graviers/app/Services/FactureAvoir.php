<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Facture;
use Help;
use PDF;

/**
 * FACTURE D'AVOIR CERTIFIÉE FNE (lot 92, 16/09/2026).
 *
 * Procédure DGI, API #2 : POST /external/invoices/{id}/refund avec les
 * identifiants FNE des articles de la facture d'origine et les quantités
 * retournées. La DGI répond par la référence de l'avoir (A…) et son lien de
 * vérification. L'avoir est alors rangé dans `facture` :
 *   · type_document AVOIR, rattaché à sa facture d'origine ;
 *   · montant NÉGATIF, part du montant de l'origine au prorata du HT crédité :
 *     tout ce qui somme des factures (créances, déjà facturé, comptabilité)
 *     se corrige de lui-même ;
 *   · jamais réclamé (totalAPayer = 0, statut de créance « Avoir »).
 * Sans certification par la DGI, aucun avoir n'est créé : l'avoir est un
 * document fiscal, il n'existe que certifié.
 */
class FactureAvoir
{
    /**
     * @param array<string, float|int|string> $quantites identifiant FNE de l'article => quantité créditée
     * @return array{success: bool, message: string, facture?: Facture, response?: array|null}
     */
    public static function emettre(Facture $origine, array $quantites, string $motif, ?int $userId = null): array
    {
        if ($origine->estUnAvoir()) {
            return ['success' => false, 'message' => "Un avoir ne s'établit pas sur un avoir."];
        }
        if (!$origine->isCertifiedFne() || empty($origine->fne_invoice_id)) {
            return ['success' => false, 'message' => "La facture d'origine n'est pas certifiée par la DGI : l'avoir ne peut pas l'être."];
        }
        $motif = trim($motif);
        if ($motif === '') {
            return ['success' => false, 'message' => "Le motif de l'avoir est obligatoire."];
        }

        $articles = collect($origine->articlesCertifies())->keyBy('id');
        $htTotal = 0.0;
        foreach ($articles as $article) {
            $htTotal += (float) $article['quantity'] * (float) $article['amount'];
        }

        $items = [];
        $lignes = [];
        $htCredite = 0.0;
        foreach ($quantites as $id => $quantite) {
            $quantite = (float) str_replace(',', '.', (string) $quantite);
            if ($quantite <= 0) {
                continue;
            }
            $article = $articles->get((string) $id);
            if (!$article) {
                return ['success' => false, 'message' => 'Article inconnu sur la facture certifiée.'];
            }
            if ($quantite > (float) $article['reste'] + 1e-9) {
                return ['success' => false, 'message' => 'Quantité supérieure à ce qui reste à créditer pour « ' . $article['description']
                    . ' » (reste ' . rtrim(rtrim(number_format((float) $article['reste'], 2, ',', ' '), '0'), ',') . ').'];
            }
            $items[] = ['id' => (string) $id, 'quantity' => $quantite];
            $lignes[] = [
                'id'              => (string) $id,
                'reference'       => $article['reference'],
                'description'     => $article['description'],
                'quantity'        => $quantite,
                'amount'          => (float) $article['amount'],
                'measurementUnit' => $article['measurementUnit'],
            ];
            $htCredite += $quantite * (float) $article['amount'];
        }
        if (!$items) {
            return ['success' => false, 'message' => 'Indiquez au moins une quantité à créditer.'];
        }

        $resultat = FneService::refundInvoice($origine, $items);
        if (!$resultat['success']) {
            return $resultat;
        }
        $body = (array) ($resultat['response'] ?? []);

        // La part du montant de l'origine (TVA, remise, transport, AIRSI compris)
        // au prorata du HT crédité : l'avoir rend au client ce qu'il a payé
        // pour ce qu'il retourne, ni plus ni moins.
        $part = $htTotal > 0 ? min(1.0, $htCredite / $htTotal) : 1.0;
        $montant = -Help::arrondiFranc(abs((float) $origine->montant) * $part);

        $avoir = Facture::create([
            'numero'                  => Help::genererNumeroUnique('facture'),
            'numero_fne'              => (string) $body['reference'],
            'type_document'           => Facture::TYPE_AVOIR,
            'facture_origine_id'      => $origine->id,
            'motif_avoir'             => $motif,
            'lignes_avoir'            => $lignes,
            // L'auteur de l'avoir, sinon celui de la facture d'origine (la colonne est obligatoire).
            'user_id'                 => $userId ?? $origine->user_id,
            'client_id'               => $origine->client_id,
            'service'                 => $origine->service,
            'service_id'              => $origine->service_id,
            'statut'                  => 2,
            'statut_creance'          => 'Avoir',
            'observations'            => 'Avoir sur la facture n° ' . $origine->numero,
            'montant'                 => $montant,
            'remise_appliquee'        => 0,
            'cout_livraison_applique' => 0,
            'tva_transport_applique'  => 0,
            'airsi_applique'          => 0,
            'fne_invoice_id'          => $body['invoice']['id'] ?? null,
            'fne_reference'           => (string) $body['reference'],
            'fne_token'               => $body['token'] ?? null,
            'fne_warning'             => (bool) ($body['warning'] ?? false),
            'fne_balance_sticker'     => $body['balance_sticker'] ?? null,
            'fne_status'              => 'certified',
            'fne_certified_at'        => now(),
            'fne_error_message'       => null,
            'fne_request_payload'     => ['items' => $items],
            'fne_response_payload'    => $body,
        ]);

        // Les données de la DGI pour le document de l'avoir (lot 96), jamais bloquant.
        try {
            DocumentDgi::donnees($avoir, true);
        } catch (\Throwable $e) {
        }

        // L'avoir part au client (lot 93), après la réponse, jamais bloquant.
        CourrielFactureFne::envoyer($avoir);

        return [
            'success'  => true,
            'message'  => "Facture d'avoir certifiée par la DGI - Réf : " . $body['reference'] . ' — envoyée au client par courriel.'
                . FneService::mentionStickers($body),
            'facture'  => $avoir,
            'response' => $body,
        ];
    }

    /** Le taux de TVA porté par les articles de la facture d'origine (d'après ce qui a été déclaré). */
    public static function tauxTvaDeLOrigine(?Facture $origine): float
    {
        $items = $origine?->fne_request_payload['items'] ?? [];
        $codes = [];
        foreach ((array) $items as $item) {
            foreach ((array) ($item['taxes'] ?? []) as $code) {
                $codes[] = strtoupper((string) $code);
            }
        }
        if (in_array('TVA', $codes, true)) {
            return (float) (Configuration::first()?->tva ?? 18);
        }
        if (in_array('TVAB', $codes, true)) {
            return 9.0;
        }

        return 0.0;
    }

    /** Le document « Facture d'avoir » (même gabarit FNE que les factures). */
    public static function pdf(Facture $avoir)
    {
        $origine = $avoir->origine;

        return PDF::loadView('document.factureAvoir', array_merge([
            'facture' => $avoir,
            'origine' => $origine,
            'config'  => Configuration::first(),
            'tauxTva' => self::tauxTvaDeLOrigine($origine),
        ], FneService::getDonneesFne($avoir, $avoir->client)))
            ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait']);
    }
}
