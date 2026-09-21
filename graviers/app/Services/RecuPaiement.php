<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Configuration;
use App\Models\DemandeLivraison;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use Help;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * LE REÇU DE PAIEMENT — UN SEUL MODÈLE, POUR TOUS LES CANAUX.
 *
 * Le reçu d'un encaissement en agence (/recu/{id}) est le modèle de référence :
 * numéro, date, agence, client, N° de l'opération, mode, référence, montant
 * encaissé, total / payé / reste, code-barres. Un paiement EN LIGNE, lui,
 * n'avait pas de reçu à ce modèle : le client recevait une « facture » vide
 * (aucune marchandise n'est encore enlevée au moment du paiement, la pièce
 * sortait à 0 partout), et sa page « Mes paiements » lui montrait un document
 * d'un autre dessin, où la désignation portait le code interne du paiement
 * plutôt que le numéro de sa commande.
 *
 * Ce service produit les données du reçu (reprises telles quelles de
 * l'écran d'encaissement), son PDF, et l'envoie au client — une seule fois
 * par règlement, quel que soit le chemin qui confirme le paiement.
 */
class RecuPaiement
{
    /** L'erreur du dernier envoi immédiat, pour l'afficher au guichet (null si réussi). */
    public static ?string $derniereErreur = null;

    /** Les données du reçu, communes à l'écran HTML, au PDF et au courriel. */
    public static function donnees(Paiement $paiement): array
    {
        $paiement->loadMissing(['client', 'client.user', 'agence', 'caissier']);

        $cmd = null;
        $libelleOperation = 'N° Commande';

        if ($paiement->service_id) {
            if ($paiement->service === Help::$COMMANDE) {
                $cmd = Commande::find($paiement->service_id);
            } elseif ($paiement->service === Help::$LOCATION) {
                $cmd = Location::find($paiement->service_id);
                $libelleOperation = 'N° Location';
            } elseif ($paiement->service === Help::$LIVRAISON) {
                $cmd = DemandeLivraison::find($paiement->service_id);
                $libelleOperation = 'N° Demande de livraison';
            }
        }

        // Rang de la tranche parmi les règlements validés de la même affaire.
        $tous = Paiement::where(function ($q) use ($cmd, $paiement) {
                if ($cmd) {
                    $q->where('service', $paiement->service)->where('service_id', $cmd->id);
                } else {
                    $q->where('id', $paiement->id);
                }
            })
            ->where('statut', 1)
            ->orderBy('created_at')
            ->get();

        $rang = $tous->search(fn ($p) => $p->id === $paiement->id);
        $trancheNum = $rang === false ? 1 : ($rang + 1);
        $trancheTotal = max(1, $tous->count());

        // Total NET dû, payé et reste : les trois modèles exposent les mêmes
        // méthodes, calculées depuis les lignes (jamais montant_total brut).
        $totalAPayer = $cmd ? $cmd->montantAPayer() : (float) $paiement->montant_total;
        $totalPaye   = $cmd ? $cmd->montantPayeComptant() : (float) $paiement->montant_total;
        $reste       = $cmd ? $cmd->montantRestantDu() : max(0, $totalAPayer - $totalPaye);

        $ligne = LignePaiement::where('paiement_id', $paiement->id)->orderBy('id')->first();
        $mode  = null;
        if ($ligne) {
            $modeObj = $ligne->mode_paiement_id ? ModePaiement::find($ligne->mode_paiement_id) : null;
            $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
        }

        return [
            'paiement'         => $paiement,
            'commande'         => $cmd,
            // Point 16 (07/09/2026) : le numéro de bon de commande du client
            // est imprimé sur le reçu, comme sur la facture.
            'bonCommande'      => $cmd instanceof Commande
                ? ($cmd->blClient?->numero ?: null)
                // Location (09/09/2026) : le numéro est figé sur la location elle-même.
                : ($cmd instanceof \App\Models\Location ? (trim((string) ($cmd->numero_bon_commande ?? '')) ?: null) : null),
            'libelleOperation' => $libelleOperation,
            'config'           => Configuration::first(),
            'trancheNum'       => $trancheNum,
            'trancheTotal'     => $trancheTotal,
            'totalAPayer'      => $totalAPayer,
            'totalPaye'        => $totalPaye,
            'reste'            => $reste,
            'mode'             => $mode ?? '-',
            'reference'        => $ligne?->reference,
            'enLigne'          => self::estEnLigne($paiement),
            'pdfMode'          => false,
        ];
    }

    /** Un règlement sans agence ni caissier a été fait en ligne (site ou mobile). */
    public static function estEnLigne(Paiement $paiement): bool
    {
        return empty($paiement->agence_id) && empty($paiement->caissier_id);
    }

    /**
     * Numéro de reçu d'un paiement en ligne : RL-AAAA-NNN, à côté des
     * RC-AAAA-NNN de l'agence. Un reçu sans numéro ne se retrouve pas.
     */
    public static function attribuerNumeroSiAbsent(Paiement $paiement): string
    {
        if ($paiement->numero_recu) {
            return $paiement->numero_recu;
        }

        $annee = $paiement->created_at ? $paiement->created_at->format('Y') : date('Y');
        $dernier = (int) Paiement::where('numero_recu', 'like', "RL-{$annee}-%")
            ->selectRaw('MAX(CAST(SUBSTRING(numero_recu, 9) AS UNSIGNED)) AS n')
            ->value('n');
        $paiement->numero_recu = sprintf('RL-%s-%03d', $annee, $dernier + 1);
        $paiement->save();

        return $paiement->numero_recu;
    }

    /** Le PDF (format A5), tel que le télécharge le guichet. */
    public static function pdf(Paiement $paiement)
    {
        @ini_set('memory_limit', '512M');

        $data = self::donnees($paiement);
        $data['pdfMode'] = true;

        return \PDF::loadView('admin.comptant.recu-pdf', $data)
            ->setPaper('A5', 'portrait')
            ->setOptions([
                'isRemoteEnabled'     => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont'         => 'DejaVu Sans',
            ]);
    }

    /**
     * Envoie le reçu au client, en PDF, UNE SEULE FOIS.
     *
     * Trois chemins confirment un paiement en ligne (retour du client, appel
     * de la passerelle, vérification planifiée) : la date d'envoi est posée
     * sur le règlement AVANT l'envoi, si bien que les autres chemins trouvent
     * le travail fait. En cas d'échec d'envoi, la date est effacée pour qu'un
     * chemin suivant réessaie.
     *
     * L'envoi est différé après la réponse HTTP (comme la facture), sauf en
     * console ou sur demande : fabriquer un PDF puis dialoguer avec le serveur
     * SMTP prendrait plusieurs secondes sur le chemin de retour de la
     * passerelle.
     */
    public static function envoyerParCourriel(Paiement $paiement, bool $immediat = false, bool $forcer = false): bool
    {
        self::$derniereErreur = null;
        $paiement = Paiement::find($paiement->id) ?: $paiement;

        // $forcer : bouton « Envoyer par courriel » de la page du reçu (11/09/2026).
        if ($paiement->recu_envoye_le && !$forcer) {
            return false;
        }

        $client = $paiement->client;
        $email  = $client?->user?->email ?: ($client?->email ?: null);
        if (!$email) {
            return false;
        }

        $paiement->recu_envoye_le = now();
        $paiement->save();

        $envoi = function () use ($paiement, $client, $email) {
            try {
                $numero = self::attribuerNumeroSiAbsent($paiement);
                $pdf    = self::pdf($paiement)->output();

                Mail::send(new \App\Mail\DocumentPdfMail(
                    ($client?->display_name ?? '') ?: 'Client',
                    $email,
                    'Reçu de paiement',
                    $numero,
                    $pdf,
                    'Recu_' . $numero . '.pdf'
                ));
            } catch (\Throwable $e) {
                self::$derniereErreur = $e->getMessage();
                Log::error('Envoi du reçu de paiement ' . $paiement->id . ' impossible : ' . $e->getMessage());
                Paiement::where('id', $paiement->id)->update(['recu_envoye_le' => null]);
            }
        };

        if ($immediat || app()->runningInConsole()) {
            $envoi();
        } else {
            app()->terminating($envoi);
        }

        return true;
    }
}
