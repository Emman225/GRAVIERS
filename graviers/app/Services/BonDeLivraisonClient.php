<?php

namespace App\Services;

use App\Mail\DocumentPdfMail;
use App\Models\Commande;
use App\Models\Enlevement;
use App\Models\Livraison;
use Carbon\Carbon;
use Help;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PDF;

/**
 * LE BON DE LIVRAISON DU CLIENT ENTREPRISE (lot 84, 15/09/2026).
 *
 * Le bon d'enlèvement (exemplaire client, gabarit livreur/bonImprime) tient
 * lieu de bon de livraison. Règles du client :
 *  - à chaque livraison, le bon part par courriel au client ENTREPRISE ;
 *  - il porte la date d'enlèvement ou de livraison de chaque ligne ;
 *  - pour une commande livrée en plusieurs enlèvements, il rappelle
 *    l'historique des enlèvements déjà faits, jusqu'à la livraison totale.
 *
 * Un seul envoi par bon (enlevement.bon_envoye_le), différé après la réponse,
 * jamais bloquant. L'application des livreurs passe par la route interne
 * (jeton), comme pour les reçus et la proforma.
 */
class BonDeLivraisonClient
{
    /** La date imprimée : livraison effective, sinon validation du fournisseur, sinon date prévue. */
    public static function dateDuBon(Enlevement $bon): ?Carbon
    {
        $livraison = $bon->livraison;
        $valeur = ($livraison?->date_livree ?? null)
            ?: ($bon->fournisseur_validation ?: null)
            ?: ($livraison?->date_livraison ?? null);
        if (!$valeur) {
            return null;
        }
        try {
            return Carbon::parse($valeur);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** La commande d'un bon de VENTE ; null pour une location ou une demande de livraison. */
    public static function commandeDe(Enlevement $bon): ?Commande
    {
        $livraison = $bon->livraison;
        if (!$livraison || (string) $livraison->provenance !== 'COMMANDE') {
            return null;
        }
        $detail = $livraison->detailCommande;

        return $detail && $detail->commande_id ? Commande::find($detail->commande_id) : null;
    }

    /**
     * Les enlèvements SERVIS de la même commande, du plus ancien au plus
     * récent, avec le cumul livré : l'historique imprimé sur le bon.
     */
    public static function historique(Enlevement $bon): array
    {
        $commande = self::commandeDe($bon);
        if (!$commande) {
            return [];
        }
        $idsLignes = $commande->detailCommande->pluck('id')->all();
        $bons = Enlevement::with(['produit', 'livraison'])
            ->whereNotNull('qte_servi')
            ->whereHas('livraison', fn ($q) => $q->whereIn('detail_commande_id', $idsLignes)->where('provenance', 'COMMANDE'))
            ->get()
            ->sortBy(fn ($b) => [self::dateDuBon($b)?->timestamp ?? 0, $b->id])
            ->values();

        $cumul  = [];
        $lignes = [];
        foreach ($bons as $b) {
            $pid = $b->produit_id;
            $cumul[$pid] = ($cumul[$pid] ?? 0) + (float) $b->qte_servi;
            $lignes[] = [
                'bon'     => $b,
                'code'    => $b->code_enleve ?: ($b->code_enlevement ?: $b->id),
                'produit' => $b->produit?->nom ?? '-',
                'unite'   => $b->produit?->unite ?? '',
                'qte'     => (float) $b->qte_servi,
                'date'    => self::dateDuBon($b),
                'cumul'   => $cumul[$pid],
                'courant' => $b->id === $bon->id,
            ];
        }

        return $lignes;
    }

    /** Par produit de la commande : commandé, livré à ce jour, reste à livrer. */
    public static function recapParProduit(Enlevement $bon, array $historique): array
    {
        $commande = self::commandeDe($bon);
        if (!$commande) {
            return [];
        }
        $livre = [];
        foreach ($historique as $l) {
            $livre[$l['bon']->produit_id] = ($livre[$l['bon']->produit_id] ?? 0) + $l['qte'];
        }
        $recap = [];
        foreach ($commande->detailCommande as $detail) {
            $commandee = (float) $detail->qte;
            $livree    = min($commandee, (float) ($livre[$detail->produit_id] ?? 0));
            $recap[] = [
                'produit'   => $detail->produit?->nom ?? '-',
                'unite'     => $detail->produit?->unite ?? '',
                'commandee' => $commandee,
                'livree'    => $livree,
                'reste'     => max(0, $commandee - $livree),
            ];
        }

        return $recap;
    }

    /** Les données du gabarit livreur/bonImprime (écran, PDF, courriel). */
    public static function donnees(Enlevement $bon): array
    {
        $historique = self::historique($bon);

        return [
            'enlevement' => $bon,
            'dateDuBon'  => self::dateDuBon($bon),
            'historique' => $historique,
            'recap'      => self::recapParProduit($bon, $historique),
            'commande'   => self::commandeDe($bon),
        ];
    }

    public static function pdf(Enlevement $bon)
    {
        return PDF::loadView('livreur.bonImprime', self::donnees($bon));
    }

    /** Le client ENTREPRISE de la course et son adresse, ou null. */
    private static function destinataire(Livraison $livraison): ?array
    {
        $client = $livraison->client;
        if (!$client || !$client->id || strtoupper((string) $client->type_client) !== 'ENTREPRISE') {
            return null;
        }
        $email = $client->user?->email ?: ($client->email ?? null);

        return $email ? ['nom' => (string) ($client->display_name ?? 'Client'), 'email' => $email] : null;
    }

    /**
     * À appeler quand une course passe « LIVREE » : pose la date de livraison
     * effective et envoie le bon au client entreprise, une seule fois par bon.
     * Vrai quand un envoi est programmé (ou fait, si $immediat).
     */
    public static function envoyerApresLivraison(Livraison $livraison, bool $immediat = false, bool $forcer = false): bool
    {
        try {
            $livraison = Livraison::find($livraison->id) ?: $livraison;
            if (Schema::hasColumn('livraison', 'date_livree') && !$livraison->date_livree
                && (string) $livraison->etat_livraison === (string) Help::$LIVRAISON_LIVREE) {
                Livraison::where('id', $livraison->id)->update(['date_livree' => now()]);
                $livraison = Livraison::find($livraison->id) ?: $livraison;
            }

            $qui = self::destinataire($livraison);
            if (!$qui) {
                return false;
            }

            $bons = Enlevement::where('livraison_id', $livraison->id)->whereNotNull('qte_servi')->get();
            $memoire = Schema::hasColumn('enlevement', 'bon_envoye_le');
            $programme = false;
            foreach ($bons as $bon) {
                if ($memoire && $bon->bon_envoye_le && !$forcer) {
                    continue;
                }
                if ($memoire) {
                    Enlevement::where('id', $bon->id)->update(['bon_envoye_le' => now()]);
                }
                $envoi = function () use ($bon, $qui, $memoire) {
                    try {
                        $code = $bon->code_enleve ?: ($bon->code_enlevement ?: (string) $bon->id);
                        Mail::send(new DocumentPdfMail(
                            $qui['nom'], $qui['email'], 'Bon de livraison', $code,
                            self::pdf(Enlevement::find($bon->id) ?: $bon)->output(),
                            'bon-de-livraison-' . $code . '.pdf'
                        ));
                    } catch (\Throwable $e) {
                        Log::warning('Bon de livraison ' . $bon->id . ' non envoyé : ' . $e->getMessage());
                        if ($memoire) {
                            Enlevement::where('id', $bon->id)->update(['bon_envoye_le' => null]);
                        }
                    }
                };
                if ($immediat || app()->runningInConsole()) {
                    $envoi();
                } else {
                    app()->terminating($envoi);
                }
                $programme = true;
            }

            return $programme;
        } catch (\Throwable $e) {
            Log::warning('Bon de livraison de la course ' . ($livraison->id ?? '?') . ' : ' . $e->getMessage());

            return false;
        }
    }
}
