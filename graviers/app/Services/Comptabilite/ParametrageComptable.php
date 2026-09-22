<?php

namespace App\Services\Comptabilite;

use App\Models\Categorie;
use App\Models\Client;
use App\Models\CompteComptable;
use App\Models\Apporteur;
use App\Models\Configuration;
use App\Models\Fournisseur;
use App\Models\HistoriqueParametrageComptable;
use App\Models\JournalComptable;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\Produit;
use App\Models\RubriqueComptable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Les règles du paramétrage comptable, en un seul endroit : l'écran, le
 * contrôle et — à partir de la phase 2 — le moteur d'écritures lisent ici.
 */
class ParametrageComptable
{
    public const LONGUEUR_MIN    = 6;
    public const LONGUEUR_MAX    = 8;
    // Huit : la longueur de la balance Sage du comptable (40110000, 41100000, 52110000).
    public const LONGUEUR_DEFAUT = 8;

    /** Les mots que le format du libellé d'écriture sait remplacer. */
    public const JETONS_LIBELLE = [
        '{type}'    => 'Facture ou Avoir',
        '{numero}'  => 'numéro de la facture',
        '{fne}'     => 'référence FNE (DGI)',
        '{client}'  => 'nom du client',
        '{affaire}' => 'numéro de la commande, de la location ou de la demande de livraison',
    ];

    public const FORMAT_LIBELLE_DEFAUT = '{type} n° {numero} / réf. FNE {fne} — {client}';

    // ------------------------------------------------------------------ réglages

    public static function longueurCompte(): int
    {
        $longueur = (int) (Configuration::first()->longueur_compte_comptable ?? self::LONGUEUR_DEFAUT);

        return ($longueur >= self::LONGUEUR_MIN && $longueur <= self::LONGUEUR_MAX) ? $longueur : self::LONGUEUR_DEFAUT;
    }

    public static function formatLibelle(): string
    {
        $format = trim((string) (Configuration::first()->format_libelle_ecriture ?? ''));

        return $format !== '' ? $format : self::FORMAT_LIBELLE_DEFAUT;
    }

    /**
     * Le numéro d'un compte est-il recevable ? Rend le message d'erreur, ou null.
     * Général : des chiffres, à la longueur du plan de comptes. Analytique :
     * lettres, chiffres, tiret, point et souligné, 20 caractères au plus.
     */
    public static function erreurDeNumero(string $nature, string $numero, ?int $longueur = null): ?string
    {
        if ($nature === CompteComptable::NATURE_GENERAL) {
            $longueur = $longueur ?? self::longueurCompte();
            if (!preg_match('/^[0-9]+$/', $numero)) {
                return 'Un compte général ne contient que des chiffres.';
            }
            if (strlen($numero) !== $longueur) {
                return "Un compte général compte {$longueur} chiffres (longueur fixée dans l'onglet « Réglages »).";
            }

            return null;
        }

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,19}$/', $numero)) {
            return 'Un compte analytique se compose de lettres, de chiffres, de tirets ou de points, 20 caractères au plus.';
        }

        return null;
    }

    /** Un compte tiers : lettres et chiffres, 3 à 20 caractères (Sage en accepte 17). */
    public static function erreurDeCompteTiers(string $compte): ?string
    {
        return preg_match('/^[A-Za-z0-9]{3,20}$/', $compte)
            ? null
            : 'Un compte tiers se compose de 3 à 20 lettres ou chiffres, sans espace.';
    }

    // ------------------------------------------------------------------ catalogue

    /** Les grandes familles : les catégories vivantes du catalogue, avec leur compte général. */
    public static function familles()
    {
        $comptes = CompteComptable::withTrashed()->get()->keyBy('id');
        $effectifs = DB::table('produit')->whereNull('deleted_at')->where('statut', \Help::$STATUT_ACTIF)
            ->whereNotNull('categorie_comptable_id')
            ->select('categorie_comptable_id', DB::raw('COUNT(*) AS nombre'))
            ->groupBy('categorie_comptable_id')->pluck('nombre', 'categorie_comptable_id');

        return Categorie::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get()
            ->each(function ($famille) use ($comptes, $effectifs) {
                $famille->compte_general = $comptes[$famille->compte_comptable_id] ?? null;
                $famille->nombre_produits_comptables = (int) ($effectifs[$famille->id] ?? 0);
            });
    }

    /** Les produits vivants, avec leurs familles du catalogue (pour proposer la famille comptable). */
    public static function produits()
    {
        return Produit::with(['categories' => function ($q) {
            $q->where('categorie.statut', \Help::$STATUT_ACTIF)->whereNull('categorie_produit.deleted_at')->orderBy('categorie.nom');
        }])->where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get();
    }

    /**
     * La famille comptable proposée pour un produit : sa première famille du
     * catalogue dans l'ordre alphabétique — la règle de l'état « Chiffre
     * d'affaires par famille ». Un produit rangé dans plusieurs familles n'a
     * qu'UN compte général : c'est pourquoi le choix est enregistré.
     */
    public static function familleProposee(Produit $produit): ?int
    {
        $premiere = $produit->categories->sortBy(fn ($c) => mb_strtolower($c->nom))->first();

        return $premiere ? (int) $premiere->id : null;
    }

    // ------------------------------------------------------------------ contrôle

    /**
     * Tout ce qui manque au paramétrage pour produire une écriture. Chaque
     * anomalie dit quoi, où (l'onglet à ouvrir) et pourquoi.
     *
     * @return array<int,array{categorie:string,objet:string,cause:string,colonne:string,onglet:string}>
     */
    public static function anomalies(): array
    {
        $anomalies = [];
        $ajouter = function (string $categorie, string $objet, string $cause, string $colonne, string $onglet) use (&$anomalies) {
            $anomalies[] = compact('categorie', 'objet', 'cause', 'colonne', 'onglet');
        };

        $comptesActifs = CompteComptable::actifs()->pluck('id')->flip();
        $famillesVivantes = Categorie::where('statut', \Help::$STATUT_ACTIF)->pluck('nom', 'id');

        foreach (self::familles() as $famille) {
            if (!$famille->compte_comptable_id) {
                $ajouter('Grande famille', $famille->nom, 'Aucun compte général en face de cette famille.', 'Compte général', 'familles');
            } elseif (!isset($comptesActifs[$famille->compte_comptable_id])) {
                $ajouter('Grande famille', $famille->nom, 'Le compte général de cette famille est désactivé ou supprimé.', 'Compte général', 'familles');
            }
        }

        $produits = Produit::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')
            ->get(['id', 'nom', 'reference', 'categorie_comptable_id', 'compte_analytique_id']);
        foreach ($produits as $produit) {
            $nom = trim($produit->nom . ($produit->reference ? ' (' . $produit->reference . ')' : ''));
            if (!$produit->categorie_comptable_id) {
                $ajouter('Produit', $nom, 'Produit sans grande famille comptable.', 'Grande famille', 'produits');
            } elseif (!isset($famillesVivantes[$produit->categorie_comptable_id])) {
                $ajouter('Produit', $nom, 'La grande famille de ce produit a été retirée du catalogue.', 'Grande famille', 'produits');
            }
            if (!$produit->compte_analytique_id) {
                $ajouter('Produit', $nom, 'Produit sans compte analytique.', 'Compte analytique', 'produits');
            } elseif (!isset($comptesActifs[$produit->compte_analytique_id])) {
                $ajouter('Produit', $nom, 'Le compte analytique de ce produit est désactivé ou supprimé.', 'Compte analytique', 'produits');
            }
        }

        foreach (RubriqueComptable::toutes() as $rubrique) {
            if ($rubrique->estFacultative() && !$rubrique->compte_comptable_id) {
                continue;   // un scénario qu'on a le droit de ne pas retenir
            }
            if (!$rubrique->compte_comptable_id) {
                $ajouter('Rubrique de facture', $rubrique->libelle, 'Aucun compte général pour cette rubrique.', 'Compte général', 'rubriques');
            } elseif (!isset($comptesActifs[$rubrique->compte_comptable_id])) {
                $ajouter('Rubrique de facture', $rubrique->libelle, 'Le compte de cette rubrique est désactivé ou supprimé.', 'Compte général', 'rubriques');
            }
        }

        $journaux = JournalComptable::actifs()->get()->keyBy('id');
        if (!$journaux->firstWhere('type', JournalComptable::TYPE_VENTES)) {
            $ajouter('Journal', 'Journal des ventes', 'Aucun journal actif de type « Ventes ».', 'Type', 'journaux');
        }
        if (!$journaux->firstWhere('type', JournalComptable::TYPE_DIVERS)) {
            $ajouter('Journal', 'Journal des opérations diverses', 'Aucun journal actif de type « Opérations diverses » : il reçoit les imputations d\'avances.', 'Type', 'journaux');
        }
        $journalCautions = Configuration::first()?->journal_cautions_id;
        if (!$journalCautions || !isset($journaux[$journalCautions]) || !$journaux[$journalCautions]->estDeTresorerie()) {
            $ajouter('Réglage', 'Journal des cautions', 'Aucun journal de trésorerie choisi pour les cautions des locations.', 'Journal des cautions', 'reglages');
        }
        foreach ($journaux as $journal) {
            if (!$journal->estDeTresorerie()) {
                continue;
            }
            if (!$journal->compte_comptable_id) {
                $ajouter('Journal', $journal->designation, 'Journal de trésorerie sans compte de trésorerie.', 'Compte de trésorerie', 'journaux');
            } elseif (!isset($comptesActifs[$journal->compte_comptable_id])) {
                $ajouter('Journal', $journal->designation, 'Le compte de trésorerie de ce journal est désactivé ou supprimé.', 'Compte de trésorerie', 'journaux');
            }
        }

        foreach (ModePaiement::where('statut', \Help::$STATUT_ACTIF)->orderBy('libelle')->get() as $mode) {
            if (!$mode->journal_comptable_id) {
                $ajouter('Mode de règlement', $mode->libelle, 'Aucun journal de trésorerie pour ce mode de règlement.', 'Journal', 'journaux');
            } elseif (!isset($journaux[$mode->journal_comptable_id])) {
                $ajouter('Mode de règlement', $mode->libelle, 'Le journal de ce mode de règlement est désactivé ou supprimé.', 'Journal', 'journaux');
            }
        }

        // Condition groupée : un « orWhere » nu échapperait au filtre des fiches supprimées.
        $sansCompte = function ($q) {
            $q->whereNull('compte_tiers')->orWhere('compte_tiers', '');
        };
        foreach (Client::where($sansCompte)->orderBy('nom')->get() as $client) {
            $ajouter('Client', $client->display_name ?: ('Client n° ' . $client->id), 'Client sans compte tiers.', 'Compte tiers', 'tiers');
        }
        foreach (Fournisseur::where($sansCompte)->orderBy('nom')->get() as $fournisseur) {
            $ajouter('Fournisseur', self::nomDuFournisseur($fournisseur), 'Fournisseur sans compte tiers.', 'Compte tiers', 'tiers');
        }
        foreach (Livreur::with('user')->where($sansCompte)->get() as $livreur) {
            $ajouter('Livreur', MoteurTresorerie::nomDuPartenaire('livreur', $livreur), 'Livreur sans compte tiers.', 'Compte tiers', 'tiers');
        }
        foreach (Apporteur::with('user')->where($sansCompte)->get() as $apporteur) {
            $ajouter('Apporteur', MoteurTresorerie::nomDuPartenaire('apporteur', $apporteur), 'Apporteur sans compte tiers.', 'Compte tiers', 'tiers');
        }

        return $anomalies;
    }

    public static function nomDuFournisseur($fournisseur): string
    {
        $nom = trim((string) ($fournisseur->nom_prenoms ?: trim($fournisseur->nom . ' ' . $fournisseur->prenom)));

        return $nom !== '' ? $nom : 'Fournisseur n° ' . $fournisseur->id;
    }

    // ------------------------------------------------------------------ historique

    /**
     * Inscrit un changement à l'historique. $avant et $apres ne portent que
     * des valeurs lisibles (numéros, libellés), jamais des identifiants : la
     * ligne doit se comprendre seule, même après la suppression du compte.
     */
    public static function journaliser(string $objet, ?int $objetId, string $designation, string $action, array $avant = [], array $apres = []): void
    {
        if ($action === 'MODIFICATION' && $avant == $apres) {
            return;
        }

        HistoriqueParametrageComptable::create([
            'user_id'     => Auth::id(),
            'objet'       => $objet,
            'objet_id'    => $objetId,
            'designation' => mb_substr($designation, 0, 190),
            'action'      => $action,
            'avant'       => $avant ?: null,
            'apres'       => $apres ?: null,
        ]);
    }
}
