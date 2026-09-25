<?php

namespace App\Services\Comptabilite;

use App\Models\Apporteur;
use App\Models\Categorie;
use App\Models\CompteComptable;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\JournalComptable;
use App\Models\Livreur;
use App\Models\Produit;
use App\Models\RubriqueComptable;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * L'IMPORT DU PARAMÉTRAGE COMPTABLE (lot 129, 26/09/2026).
 *
 * Le paramétrage complet — comptes, familles, produits, rubriques, comptes
 * tiers, journaux, modes de règlement — représente des centaines de cases à
 * remplir une par une. Le responsable a demandé de pouvoir le poser d'un
 * fichier : un classeur au format imposé, que chaque entreprise remplit, et
 * qui règle tout en une fois.
 *
 * TROIS RÈGLES, QUI NE CHANGENT PAS :
 *  1. L'import ne SUPPRIME jamais rien. Il crée ce qui manque et corrige ce
 *     que le fichier nomme ; ce que le fichier ne nomme pas reste tel quel.
 *  2. Une ligne qu'on ne sait pas rattacher est REFUSÉE avec sa cause, et le
 *     reste du fichier passe quand même. Un fichier n'est jamais « à moitié
 *     rejeté » sans qu'on sache pourquoi.
 *  3. On ANALYSE avant d'appliquer : le même parcours, sans écrire, rend le
 *     compte rendu ligne à ligne. Rejoué, un import n'ajoute rien.
 */
class ImportParametrage
{
    /** L'ordre compte : les comptes d'abord, tout le reste s'y rattache. */
    public const FEUILLES = [
        'Comptes'             => 'comptes',
        'Grandes familles'    => 'familles',
        'Produits'            => 'produits',
        'Rubriques'           => 'rubriques',
        'Comptes tiers'       => 'tiers',
        'Journaux'            => 'journaux',
        'Modes de règlement'  => 'modes',
    ];

    public const CREATION   = 'Création';
    public const CORRECTION = 'Correction';
    public const INCHANGE   = 'Inchangé';
    public const REFUS      = 'Refusé';

    /** Les familles de tiers, et le modèle qui les porte. */
    public const TIERS = [
        'client'      => Client::class,
        'fournisseur' => Fournisseur::class,
        'livreur'     => Livreur::class,
        'apporteur'   => Apporteur::class,
    ];

    /** Lecture seule : ce que l'import ferait, ligne à ligne. */
    public static function analyser(string $chemin): array
    {
        return self::parcourir($chemin, false);
    }

    /** Le même parcours, en écrivant cette fois. */
    public static function appliquer(string $chemin): array
    {
        return DB::transaction(fn () => self::parcourir($chemin, true));
    }

    // ================================================================== le parcours

    private static function parcourir(string $chemin, bool $ecrire): array
    {
        $classeur = Excel::toArray(null, $chemin);
        $feuilles = self::parNom($chemin, $classeur);

        $resultat = [];
        foreach (self::FEUILLES as $nom => $methode) {
            $lignes = $feuilles[self::cle($nom)] ?? null;
            if ($lignes === null) {
                $resultat[$nom] = ['absente' => true, 'lignes' => []];
                continue;
            }
            $resultat[$nom] = ['absente' => false, 'lignes' => self::{$methode}(self::corps($lignes), $ecrire)];
        }

        return $resultat;
    }

    /** Les feuilles rangées par nom : le classeur ne dit pas les noms, le lecteur si. */
    private static function parNom(string $chemin, array $classeur): array
    {
        $noms = [];
        try {
            $lecteur = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($chemin);
            $lecteur->setReadDataOnly(true);
            $noms = $lecteur->listWorksheetNames($chemin);
        } catch (\Throwable $e) {
            $noms = [];
        }

        $feuilles = [];
        foreach (array_values($classeur) as $i => $lignes) {
            $nom = $noms[$i] ?? ('Feuille ' . ($i + 1));
            $feuilles[self::cle($nom)] = $lignes;
        }

        return $feuilles;
    }

    /** Sans la ligne d'en-têtes, et sans les lignes vides. */
    private static function corps(array $lignes): array
    {
        $corps = [];
        foreach ($lignes as $rang => $ligne) {
            if ($rang === 0) {
                continue;   // les en-têtes
            }
            $valeurs = array_map(fn ($v) => trim((string) $v), $ligne);
            if (implode('', $valeurs) === '') {
                continue;   // une ligne vide
            }
            $corps[] = ['rang' => $rang + 1, 'valeurs' => $valeurs];
        }

        return $corps;
    }

    private static function cle(string $texte): string
    {
        $texte = mb_strtolower(trim($texte));

        return preg_replace('/[^a-z0-9]+/', '', self::sansAccents($texte));
    }

    private static function sansAccents(string $texte): string
    {
        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
    }

    private static function col(array $valeurs, int $i): string
    {
        return trim((string) ($valeurs[$i] ?? ''));
    }

    private static function resultat(int $rang, string $action, string $objet, string $detail = ''): array
    {
        return ['rang' => $rang, 'action' => $action, 'objet' => $objet, 'detail' => $detail];
    }

    // ================================================================== les feuilles

    /** Numéro | Intitulé | Nature */
    private static function comptes(array $corps, bool $ecrire): array
    {
        $sortie = [];
        foreach ($corps as $ligne) {
            $numero  = self::col($ligne['valeurs'], 0);
            $libelle = self::col($ligne['valeurs'], 1);
            $nature  = self::cle(self::col($ligne['valeurs'], 2)) === 'analytique'
                ? CompteComptable::NATURE_ANALYTIQUE : CompteComptable::NATURE_GENERAL;

            if ($numero === '' || $libelle === '') {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $numero ?: '(sans numéro)', 'Le numéro et l\'intitulé sont tous les deux obligatoires.');
                continue;
            }
            if ($erreur = ParametrageComptable::erreurDeNumero($nature, $numero)) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $numero, $erreur);
                continue;
            }

            $existant = CompteComptable::withTrashed()->where('numero', $numero)->first();
            if (!$existant) {
                $sortie[] = self::resultat($ligne['rang'], self::CREATION, $numero, $libelle);
                if ($ecrire) {
                    CompteComptable::create(['nature' => $nature, 'numero' => $numero, 'libelle' => $libelle, 'statut' => 1]);
                }
            } elseif ($existant->libelle !== $libelle || $existant->nature !== $nature) {
                $sortie[] = self::resultat($ligne['rang'], self::CORRECTION, $numero, $existant->libelle . ' → ' . $libelle);
                if ($ecrire) {
                    $existant->update(['libelle' => $libelle, 'nature' => $nature]);
                }
            } else {
                $sortie[] = self::resultat($ligne['rang'], self::INCHANGE, $numero, $libelle);
            }
        }

        return $sortie;
    }

    /** Grande famille | Compte général */
    private static function familles(array $corps, bool $ecrire): array
    {
        $sortie = [];
        foreach ($corps as $ligne) {
            $nom    = self::col($ligne['valeurs'], 0);
            $numero = self::col($ligne['valeurs'], 1);

            $famille = Categorie::where('nom', $nom)->where('statut', 1)->first();
            if (!$famille) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $nom, 'Aucune grande famille active ne porte ce nom dans le catalogue.');
                continue;
            }
            [$compte, $erreur] = self::compteGeneral($numero);
            if ($erreur) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $nom, $erreur);
                continue;
            }

            $sortie[] = self::poser($ligne['rang'], $nom, (int) $famille->compte_comptable_id, $compte?->id,
                fn () => DB::table('categorie')->where('id', $famille->id)->update(['compte_comptable_id' => $compte?->id]), $ecrire, $numero);
        }

        return $sortie;
    }

    /** Référence | Produit | Grande famille | Compte analytique */
    private static function produits(array $corps, bool $ecrire): array
    {
        $sortie = [];
        foreach ($corps as $ligne) {
            $reference = self::col($ligne['valeurs'], 0);
            $nomFamille = self::col($ligne['valeurs'], 2);
            $numero = self::col($ligne['valeurs'], 3);

            $produit = $reference !== '' ? Produit::where('reference', $reference)->first() : null;
            if (!$produit) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $reference ?: self::col($ligne['valeurs'], 1),
                    'Aucun produit ne porte cette référence. La référence ne s\'invente pas : la reprendre du catalogue.');
                continue;
            }

            $famille = $nomFamille !== '' ? Categorie::where('nom', $nomFamille)->where('statut', 1)->first() : null;
            if ($nomFamille !== '' && !$famille) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $reference, 'Grande famille inconnue : ' . $nomFamille);
                continue;
            }
            [$analytique, $erreur] = self::compteAnalytique($numero);
            if ($erreur) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $reference, $erreur);
                continue;
            }

            $avant = [(int) $produit->categorie_comptable_id, (int) $produit->compte_analytique_id];
            $apres = [(int) ($famille?->id), (int) ($analytique?->id)];
            if ($avant === $apres) {
                $sortie[] = self::resultat($ligne['rang'], self::INCHANGE, $reference, $produit->nom);
                continue;
            }
            $sortie[] = self::resultat($ligne['rang'], $avant === [0, 0] ? self::CREATION : self::CORRECTION, $reference,
                trim(($nomFamille ?: '—') . ' · ' . ($numero ?: '—')));
            if ($ecrire) {
                DB::table('produit')->where('id', $produit->id)->update([
                    'categorie_comptable_id' => $famille?->id,
                    'compte_analytique_id'   => $analytique?->id,
                ]);
            }
        }

        return $sortie;
    }

    /** Code | Rubrique | Compte général | Compte analytique */
    private static function rubriques(array $corps, bool $ecrire): array
    {
        $sortie = [];
        foreach ($corps as $ligne) {
            $code = mb_strtoupper(self::col($ligne['valeurs'], 0));
            $rubrique = RubriqueComptable::where('code', $code)->first();
            if (!$rubrique) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $code ?: '(sans code)',
                    'Code de rubrique inconnu. Les codes sont ceux du modèle, ils ne s\'inventent pas.');
                continue;
            }

            [$compte, $erreur] = self::compteGeneral(self::col($ligne['valeurs'], 2));
            if ($erreur) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $rubrique->libelle, $erreur);
                continue;
            }
            [$analytique, $erreur2] = self::compteAnalytique(self::col($ligne['valeurs'], 3));
            if ($erreur2) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $rubrique->libelle, $erreur2);
                continue;
            }

            $avant = [(int) $rubrique->compte_comptable_id, (int) $rubrique->compte_analytique_id];
            $apres = [(int) ($compte?->id), (int) ($analytique?->id)];
            if ($avant === $apres) {
                $sortie[] = self::resultat($ligne['rang'], self::INCHANGE, $rubrique->libelle, (string) $compte?->numero);
                continue;
            }
            $sortie[] = self::resultat($ligne['rang'], $avant[0] === 0 ? self::CREATION : self::CORRECTION,
                $rubrique->libelle, (string) $compte?->numero);
            if ($ecrire) {
                $rubrique->update([
                    'compte_comptable_id'  => $compte?->id,
                    'compte_analytique_id' => $rubrique->accepteUnAnalytique() ? $analytique?->id : null,
                ]);
            }
        }

        return $sortie;
    }

    /** Type | Identifiant | Nom | Compte tiers */
    private static function tiers(array $corps, bool $ecrire): array
    {
        $sortie = [];
        foreach ($corps as $ligne) {
            $type = self::cle(self::col($ligne['valeurs'], 0));
            $identifiant = self::col($ligne['valeurs'], 1);
            $compte = self::col($ligne['valeurs'], 3);

            if (!isset(self::TIERS[$type])) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $identifiant,
                    'Type de tiers inconnu : attendu client, fournisseur, livreur ou apporteur.');
                continue;
            }
            $classe = self::TIERS[$type];
            $modele = $identifiant !== '' ? $classe::withTrashed()->find((int) $identifiant) : null;
            if (!$modele) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $identifiant ?: '(sans identifiant)',
                    'Aucun ' . $type . ' ne porte cet identifiant. Reprendre celui du modèle.');
                continue;
            }
            if ($compte !== '' && ($erreur = ParametrageComptable::erreurDeCompteTiers($compte))) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $identifiant, $erreur);
                continue;
            }

            $nom = self::col($ligne['valeurs'], 2) ?: ($type . ' n° ' . $identifiant);
            $sortie[] = self::poser($ligne['rang'], $nom, $modele->compte_tiers, $compte ?: null,
                fn () => DB::table($modele->getTable())->where('id', $modele->id)->update(['compte_tiers' => $compte ?: null]),
                $ecrire, $compte);
        }

        return $sortie;
    }

    /** Code | Libellé | Type | Compte de trésorerie */
    private static function journaux(array $corps, bool $ecrire): array
    {
        $sortie = [];
        foreach ($corps as $ligne) {
            $code = mb_strtoupper(self::col($ligne['valeurs'], 0));
            $libelle = self::col($ligne['valeurs'], 1);
            $type = mb_strtoupper(self::sansAccents(mb_strtolower(self::col($ligne['valeurs'], 2))));
            $type = str_replace([' ', '-'], '_', $type);
            $numero = self::col($ligne['valeurs'], 3);

            if ($code === '' || $libelle === '') {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $code ?: '(sans code)', 'Le code et le libellé sont obligatoires.');
                continue;
            }
            if (!array_key_exists($type, JournalComptable::TYPES)) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $code,
                    'Type de journal inconnu : attendu ' . implode(', ', array_keys(JournalComptable::TYPES)) . '.');
                continue;
            }
            [$compte, $erreur] = self::compteGeneral($numero);
            if ($erreur) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $code, $erreur);
                continue;
            }

            $journal = JournalComptable::withTrashed()->where('code', $code)->first();
            $estTresorerie = in_array($type, JournalComptable::TYPES_DE_TRESORERIE, true);
            $compteId = $estTresorerie ? $compte?->id : null;

            if (!$journal) {
                $sortie[] = self::resultat($ligne['rang'], self::CREATION, $code, $libelle);
                if ($ecrire) {
                    JournalComptable::create(['code' => $code, 'libelle' => $libelle, 'type' => $type,
                        'compte_comptable_id' => $compteId, 'statut' => 1]);
                }
                continue;
            }
            $avant = [$journal->libelle, $journal->type, (int) $journal->compte_comptable_id];
            $apres = [$libelle, $type, (int) $compteId];
            if ($avant === $apres) {
                $sortie[] = self::resultat($ligne['rang'], self::INCHANGE, $code, $libelle);
                continue;
            }
            $sortie[] = self::resultat($ligne['rang'], self::CORRECTION, $code, $libelle . ' · ' . ($numero ?: '—'));
            if ($ecrire) {
                $journal->update(['libelle' => $libelle, 'type' => $type, 'compte_comptable_id' => $compteId]);
            }
        }

        return $sortie;
    }

    /** Mode de règlement | Code journal */
    private static function modes(array $corps, bool $ecrire): array
    {
        $sortie = [];
        foreach ($corps as $ligne) {
            $libelle = self::col($ligne['valeurs'], 0);
            $code = mb_strtoupper(self::col($ligne['valeurs'], 1));

            $mode = DB::table('mode_paiement')->whereNull('deleted_at')->where('libelle', $libelle)->first();
            if (!$mode) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $libelle ?: '(sans libellé)',
                    'Aucun mode de règlement ne porte ce libellé.');
                continue;
            }
            $journal = $code !== '' ? JournalComptable::where('code', $code)->first() : null;
            if ($code !== '' && !$journal) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $libelle, 'Journal inconnu : ' . $code);
                continue;
            }
            if ($journal && !$journal->estDeTresorerie()) {
                $sortie[] = self::resultat($ligne['rang'], self::REFUS, $libelle,
                    'Le journal ' . $code . ' n\'est pas un journal de trésorerie : un encaissement ne peut pas y entrer.');
                continue;
            }

            $sortie[] = self::poser($ligne['rang'], $libelle, (int) $mode->journal_comptable_id, $journal?->id,
                fn () => DB::table('mode_paiement')->where('id', $mode->id)->update(['journal_comptable_id' => $journal?->id]),
                $ecrire, $code);
        }

        return $sortie;
    }

    // ================================================================== l'outillage

    /**
     * Pose une valeur si elle change, et dit ce qui s'est passé.
     *
     * « Vide » a plusieurs visages — null, chaîne vide, entier zéro : sans les
     * ramener au même, une case vide « changerait » en case vide et l'import
     * annoncerait des créations qui n'en sont pas.
     */
    private static function poser(int $rang, string $objet, $avant, $apres, callable $ecriture, bool $ecrire, string $detail): array
    {
        $avant = self::vide($avant);
        $apres = self::vide($apres);
        if ($avant === $apres) {
            return self::resultat($rang, self::INCHANGE, $objet, $detail);
        }
        if ($ecrire) {
            $ecriture();
        }

        return self::resultat($rang, $avant === '' ? self::CREATION : self::CORRECTION, $objet, $detail);
    }

    private static function vide($valeur): string
    {
        return ($valeur === null || $valeur === '' || $valeur === 0 || $valeur === '0') ? '' : (string) $valeur;
    }

    /** @return array{0: ?CompteComptable, 1: ?string} le compte, ou la cause du refus */
    private static function compteGeneral(string $numero): array
    {
        if ($numero === '') {
            return [null, null];
        }
        $compte = CompteComptable::generaux()->where('numero', $numero)->first();
        if (!$compte) {
            return [null, 'Compte général inconnu : ' . $numero . '. Le créer d\'abord dans la feuille « Comptes ».'];
        }

        return [$compte, null];
    }

    /** @return array{0: ?CompteComptable, 1: ?string} */
    private static function compteAnalytique(string $numero): array
    {
        if ($numero === '') {
            return [null, null];
        }
        $compte = CompteComptable::analytiques()->where('numero', $numero)->first();
        if (!$compte) {
            return [null, 'Compte analytique inconnu : ' . $numero . '. Le créer d\'abord dans la feuille « Comptes ».'];
        }

        return [$compte, null];
    }

    /** Le compte rendu, résumé par action : c'est ce qu'on lit en premier. */
    public static function resume(array $rapport): array
    {
        $resume = [self::CREATION => 0, self::CORRECTION => 0, self::INCHANGE => 0, self::REFUS => 0];
        foreach ($rapport as $feuille) {
            foreach ($feuille['lignes'] as $ligne) {
                $resume[$ligne['action']] = ($resume[$ligne['action']] ?? 0) + 1;
            }
        }

        return $resume;
    }
}
