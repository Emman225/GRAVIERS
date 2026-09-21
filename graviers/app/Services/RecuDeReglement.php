<?php

namespace App\Services;

use App\Http\Controllers\CommandeComptantController;
use App\Http\Controllers\CreanceClientTermeController;
use App\Http\Controllers\DemandeLivraisonComptantController;
use App\Http\Controllers\DetteApporteurController;
use App\Http\Controllers\DetteFournisseurController;
use App\Http\Controllers\DetteLivreurController;
use App\Http\Controllers\LocationComptantController;
use App\Models\DemandePaiement;
use App\Models\Paiement;
use App\Models\PaiementApporteur;
use App\Models\PaiementFournisseur;
use App\Models\PaiementLivreur;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * LE REÇU D'UN RÈGLEMENT DE GUICHET PART PAR COURRIEL (11/09/2026).
 *
 * Le back-office génère un reçu (encaissements, créances des clients à terme)
 * ou un bordereau (dettes des fournisseurs, livreurs, apporteurs) une fois le
 * règlement FINALISÉ — « Effectuée », au bout du circuit de preuve. Ce même
 * document, en PDF, est envoyé au client ou au partenaire, une seule fois.
 *
 * RÈGLE ABSOLUE : UN ENVOI QUI ÉCHOUE N'EMPÊCHE JAMAIS L'OPÉRATION.
 *  - la finalisation est enregistrée AVANT que l'envoi ne soit seulement
 *    préparé ;
 *  - chaque étape (recherche du destinataire, fabrication du PDF, envoi) est
 *    sous try/catch : une erreur se journalise, rien ne remonte ;
 *  - l'envoi est différé après la réponse HTTP (comme les autres courriels),
 *    sauf en console ou sur demande.
 */
class RecuDeReglement
{
    /** Le guichet de chaque contrôleur : ce qu'il règle, et à qui va le reçu. */
    private const GUICHETS = [
        CommandeComptantController::class         => 'comptant',
        LocationComptantController::class         => 'comptant',
        DemandeLivraisonComptantController::class => 'comptant',
        CreanceClientTermeController::class       => 'terme',
        DetteFournisseurController::class         => 'fournisseur',
        DetteLivreurController::class             => 'livreur',
        DetteApporteurController::class           => 'apporteur',
    ];

    /** Modèle, contrôleur (données du reçu) et relation vers le tiers, par guichet partenaire. */
    private const PARTENAIRES = [
        'fournisseur' => [PaiementFournisseur::class, DetteFournisseurController::class, 'fournisseur'],
        'livreur'     => [PaiementLivreur::class,     DetteLivreurController::class,     'livreur'],
        'apporteur'   => [PaiementApporteur::class,   DetteApporteurController::class,   'apporteur'],
    ];

    /** @var array<string, bool> Présence de la colonne recu_envoye_le, par table. */
    private static array $colonne = [];

    /**
     * Appelé par PreuveDeReglementPartenaire::effectuerReglement, une fois le
     * règlement EFFECTUÉ, avec la classe du contrôleur qui finalise.
     */
    public static function envoyerApresFinalisation($paiement, string $controleur, bool $immediat = false): bool
    {
        try {
            $guichet = self::GUICHETS[ltrim($controleur, '\\')] ?? null;
            if (!$guichet || !$paiement) {
                return false;
            }

            return match ($guichet) {
                'comptant' => RecuPaiement::envoyerParCourriel($paiement, $immediat),
                'terme'    => self::envoyerAuClientATerme($paiement, $immediat),
                default    => self::envoyerAuPartenaire($paiement, $guichet, $immediat),
            };
        } catch (\Throwable $e) {
            Log::warning('Reçu du règlement ' . ($paiement->id ?? '?') . ' (' . $controleur . ') non envoyé : ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Les bordereaux des règlements nés d'une demande de paiement d'un
     * partenaire (colonne demande_paiement_id), quand la demande est finalisée.
     */
    public static function envoyerPourLaDemande(DemandePaiement $demande, bool $immediat = false): int
    {
        $envoyes = 0;
        foreach (self::PARTENAIRES as $guichet => [$classe]) {
            try {
                if (!Schema::hasColumn((new $classe)->getTable(), 'demande_paiement_id')) {
                    continue;
                }
                foreach ($classe::where('demande_paiement_id', $demande->id)->get() as $p) {
                    if (self::envoyerAuPartenaire($p, $guichet, $immediat)) {
                        $envoyes++;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Bordereaux de la demande de paiement ' . $demande->id . ' (' . $guichet . ') : ' . $e->getMessage());
            }
        }

        return $envoyes;
    }

    /** Le reçu d'un encaissement de client à terme, au client. */
    public static function envoyerAuClientATerme(Paiement $paiement, bool $immediat = false): bool
    {
        try {
            $paiement = Paiement::find($paiement->id) ?: $paiement;
            $client   = $paiement->client;
            $email    = self::courriel($client?->user?->email, $client?->email);

            return self::expedier(
                $paiement,
                $email,
                fn () => app(CreanceClientTermeController::class)->donneesDuRecu($paiement),
                $immediat
            );
        } catch (\Throwable $e) {
            Log::warning('Reçu du règlement à terme ' . $paiement->id . ' non envoyé : ' . $e->getMessage());

            return false;
        }
    }

    /** Le bordereau d'un règlement de dette, au fournisseur, au livreur ou à l'apporteur. */
    public static function envoyerAuPartenaire($paiement, string $guichet, bool $immediat = false): bool
    {
        try {
            [$classe, $controleur, $relation] = self::PARTENAIRES[$guichet] ?? [null, null, null];
            if (!$classe || !$paiement) {
                return false;
            }
            $paiement = $classe::find($paiement->id) ?: $paiement;
            $tiers    = $paiement->{$relation};
            $email    = self::courriel($tiers?->user?->email, $tiers?->email ?? null);

            return self::expedier(
                $paiement,
                $email,
                fn () => app($controleur)->donneesDuRecu($paiement),
                $immediat
            );
        } catch (\Throwable $e) {
            Log::warning('Bordereau du règlement ' . $guichet . ' ' . ($paiement->id ?? '?') . ' non envoyé : ' . $e->getMessage());

            return false;
        }
    }

    /** Le premier courriel utilisable, ou rien. */
    private static function courriel(?string ...$candidats): ?string
    {
        foreach ($candidats as $c) {
            $c = trim((string) $c);
            if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) {
                return $c;
            }
        }

        return null;
    }

    private static function aLaColonne(string $table): bool
    {
        if (!array_key_exists($table, self::$colonne)) {
            try {
                self::$colonne[$table] = Schema::hasColumn($table, 'recu_envoye_le');
            } catch (\Throwable $e) {
                self::$colonne[$table] = false;
            }
        }

        return self::$colonne[$table];
    }

    /**
     * L'envoi lui-même : la date est posée AVANT (les autres chemins se
     * taisent), effacée si l'envoi échoue (un chemin suivant réessaie). Le
     * PDF est celui que le guichet télécharge (admin.shared.recu-paiement-pdf).
     */
    private static function expedier($modele, ?string $email, \Closure $donnees, bool $immediat): bool
    {
        if (!$email) {
            return false;
        }

        $classe  = get_class($modele);
        $table   = $modele->getTable();
        $memoire = self::aLaColonne($table);

        if ($memoire && $modele->recu_envoye_le) {
            return false;
        }
        if ($memoire) {
            $classe::where('id', $modele->id)->update(['recu_envoye_le' => now()]);
        }

        $envoi = function () use ($modele, $classe, $email, $donnees, $memoire) {
            try {
                @ini_set('memory_limit', '512M');

                $data = $donnees();
                $data['pdfMode'] = true;

                $titre  = (string) ($data['titre'] ?? 'REÇU DE PAIEMENT');
                $type   = mb_strtoupper(mb_substr($titre, 0, 1)) . mb_strtolower(mb_substr($titre, 1));
                $numero = (string) ($data['numeroRecu'] ?? $modele->id);
                $nom    = trim((string) ($data['beneficiaireNom'] ?? '')) ?: 'Partenaire';
                if ($nom === '-') {
                    $nom = 'Partenaire';
                }

                $pdf = \PDF::loadView('admin.shared.recu-paiement-pdf', $data)
                    ->setPaper('A5', 'portrait')
                    ->setOptions([
                        'isRemoteEnabled'      => false,
                        'isHtml5ParserEnabled' => true,
                        'defaultFont'          => 'DejaVu Sans',
                    ])
                    ->output();

                Mail::send(new \App\Mail\DocumentPdfMail(
                    $nom,
                    $email,
                    $type,
                    $numero,
                    $pdf,
                    'Recu_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $numero) . '.pdf'
                ));
            } catch (\Throwable $e) {
                Log::error('Envoi du reçu du règlement ' . $modele->id . ' (' . $classe . ') impossible : ' . $e->getMessage());
                if ($memoire) {
                    try {
                        $classe::where('id', $modele->id)->update(['recu_envoye_le' => null]);
                    } catch (\Throwable $e2) {
                        // Rien : l'opération est acquise, seul le reçu manque.
                    }
                }
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
