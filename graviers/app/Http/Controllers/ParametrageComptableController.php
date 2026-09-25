<?php

namespace App\Http\Controllers;

use App\Models\Apporteur;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\CompteComptable;
use App\Models\Configuration;
use App\Models\Fournisseur;
use App\Models\HistoriqueParametrageComptable;
use App\Models\JournalComptable;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\Produit;
use App\Models\RubriqueComptable;
use App\Services\Comptabilite\MoteurTresorerie;
use App\Services\Comptabilite\ImportParametrage;
use App\Services\Comptabilite\ParametrageComptable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Paramétrage comptable (module « Écritures comptables », phase 1, lot 116).
 * Réservé aux administrateurs : les écritures sont produites par et pour
 * DALAKOUN, exploitant de la plateforme.
 */
class ParametrageComptableController extends Controller
{
    public const ONGLETS = ['comptes', 'familles', 'produits', 'rubriques', 'tiers', 'journaux', 'reglages', 'import', 'controle', 'historique'];

    /**
     * Le plan comptable au format d'import de Sage (gabarit du 26/09/2026).
     * Les comptes généraux seulement : dans Sage, les comptes tiers et les
     * sections analytiques s'importent par d'autres fichiers.
     */
    /**
     * Le classeur de paramétrage, DÉJÀ REMPLI de ce qui est réglé aujourd'hui :
     * l'entreprise corrige au lieu de tout taper, et le même fichier sert de
     * sauvegarde du paramétrage.
     */
    public function modeleImport()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ModeleParametrageExport(),
            'parametrage-comptable-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /** Premier temps : on lit le fichier et on dit ce qu'il ferait. Rien n'est écrit. */
    public function analyserImport(Request $request)
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:8192'],
        ], [], ['fichier' => 'classeur de paramétrage']);

        $chemin = $request->file('fichier')->store('imports-comptables');
        $rapport = ImportParametrage::analyser(storage_path('app/' . $chemin));

        return view('comptabilite.parametrage.import', [
            'rapport' => $rapport,
            'resume'  => ImportParametrage::resume($rapport),
            'fichier' => $chemin,
            'applique' => false,
        ]);
    }

    /** Second temps : le même parcours, en écrivant. Le fichier est ensuite retiré. */
    public function appliquerImport(Request $request)
    {
        $request->validate(['fichier' => ['required', 'string']]);
        $chemin = storage_path('app/' . $request->input('fichier'));

        // Le chemin vient d'un champ caché : on refuse tout ce qui sort du
        // dossier des imports.
        if (!str_starts_with($request->input('fichier'), 'imports-comptables/') || !is_file($chemin)) {
            return back()->with('erreurImport', "Le fichier analysé n’est plus disponible : recommencer l’import.");
        }

        $rapport = ImportParametrage::appliquer($chemin);
        @unlink($chemin);
        ParametrageComptable::journaliser('import', null, basename($chemin), 'IMPORT', [], ImportParametrage::resume($rapport));

        return view('comptabilite.parametrage.import', [
            'rapport' => $rapport,
            'resume'  => ImportParametrage::resume($rapport),
            'fichier' => null,
            'applique' => true,
        ]);
    }

    public function exporterPlanSage()
    {
        $comptes = CompteComptable::generaux()->orderBy('numero')->get();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PlanComptableSageExport($comptes),
            'plan-comptable-sage-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function index(Request $request)
    {
        $onglet = in_array($request->query('onglet'), self::ONGLETS, true) ? $request->query('onglet') : 'comptes';

        $comptes = CompteComptable::orderBy('nature')->orderBy('numero')->get();
        $anomalies = ParametrageComptable::anomalies();

        return view('comptabilite.parametrage.index', [
            'onglet'            => $onglet,
            'comptes'           => $comptes,
            'comptesGeneraux'   => $comptes->where('nature', CompteComptable::NATURE_GENERAL)->where('statut', 1)->values(),
            'comptesAnalytiques' => $comptes->where('nature', CompteComptable::NATURE_ANALYTIQUE)->where('statut', 1)->values(),
            'familles'          => ParametrageComptable::familles(),
            'produits'          => ParametrageComptable::produits(),
            'rubriques'         => RubriqueComptable::toutes(),
            'clients'           => Client::orderBy('nom')->get(),
            'fournisseurs'      => Fournisseur::orderBy('nom')->get(),
            'livreurs'          => Livreur::with('user')->get()->sortBy(fn ($l) => MoteurTresorerie::nomDuPartenaire('livreur', $l))->values(),
            'apporteurs'        => Apporteur::with('user')->get()->sortBy(fn ($a) => MoteurTresorerie::nomDuPartenaire('apporteur', $a))->values(),
            'journalCautions'   => Configuration::first()?->journal_cautions_id,
            'journaux'          => JournalComptable::with('compte')->orderBy('code')->get(),
            'modes'             => ModePaiement::where('statut', \Help::$STATUT_ACTIF)->orderBy('libelle')->get(),
            'longueur'          => ParametrageComptable::longueurCompte(),
            'formatLibelle'     => ParametrageComptable::formatLibelle(),
            'anomalies'         => $anomalies,
            'anomaliesParOnglet' => collect($anomalies)->countBy('onglet'),
            'historique'        => HistoriqueParametrageComptable::with('user')->orderByDesc('id')->limit(500)->get(),
        ]);
    }

    /** Les quatre sortes de tiers qui portent un compte. */
    private const TIERS = ['client' => Client::class, 'fournisseur' => Fournisseur::class, 'livreur' => Livreur::class, 'apporteur' => Apporteur::class];

    private function nomDuTiers(string $objet, $tiers): string
    {
        return $objet === 'client'
            ? ($tiers->display_name ?: 'Client n° ' . $tiers->id)
            : MoteurTresorerie::nomDuPartenaire($objet, $tiers);
    }

    private function retour(string $onglet)
    {
        return redirect()->route('show.comptabilite.parametrage', ['onglet' => $onglet]);
    }

    // ================================================================== comptes

    public function compteCreate(Request $request)
    {
        $nature = $request->query('nature') === CompteComptable::NATURE_ANALYTIQUE
            ? CompteComptable::NATURE_ANALYTIQUE : CompteComptable::NATURE_GENERAL;

        return view('comptabilite.parametrage.compte', [
            'compte'   => new CompteComptable(['nature' => $nature, 'statut' => 1]),
            'mode'     => 'create',
            'longueur' => ParametrageComptable::longueurCompte(),
        ]);
    }

    public function compteEdit(CompteComptable $compte)
    {
        return view('comptabilite.parametrage.compte', [
            'compte'   => $compte,
            'mode'     => 'edit',
            'longueur' => ParametrageComptable::longueurCompte(),
            'emplois'  => $compte->emplois(),
        ]);
    }

    private function validerCompte(Request $request, ?CompteComptable $existant = null): array
    {
        $donnees = $request->validate([
            'nature'  => ['required', Rule::in(array_keys(CompteComptable::NATURES))],
            'numero'  => ['required', 'string', 'max:20'],
            'libelle' => ['required', 'string', 'max:150'],
        ], [], ['nature' => 'nature', 'numero' => 'numéro', 'libelle' => 'libellé']);

        $donnees['numero']  = strtoupper(trim($donnees['numero']));
        $donnees['libelle'] = trim($donnees['libelle']);

        $erreur = ParametrageComptable::erreurDeNumero($donnees['nature'], $donnees['numero']);

        if (!$erreur) {
            $doublon = CompteComptable::where('nature', $donnees['nature'])->where('numero', $donnees['numero'])
                ->when($existant, fn ($q) => $q->where('id', '!=', $existant->id))->exists();
            if ($doublon) {
                $erreur = 'Ce numéro existe déjà dans le plan de comptes.';
            }
        }

        if ($erreur) {
            throw \Illuminate\Validation\ValidationException::withMessages(['numero' => $erreur]);
        }

        return $donnees;
    }

    public function compteStore(Request $request)
    {
        $donnees = $this->validerCompte($request);
        $compte = CompteComptable::create($donnees + ['statut' => 1]);

        ParametrageComptable::journaliser('compte', $compte->id, $compte->designation, 'CREATION', [], [
            'nature' => CompteComptable::NATURES[$compte->nature], 'numéro' => $compte->numero, 'libellé' => $compte->libelle,
        ]);

        return $this->retour('comptes')->with('success', "Compte {$compte->numero} créé.");
    }

    public function compteUpdate(Request $request, CompteComptable $compte)
    {
        $request->merge(['nature' => $compte->nature]);   // la nature d'un compte ne change pas
        $donnees = $this->validerCompte($request, $compte);

        $avant = ['numéro' => $compte->numero, 'libellé' => $compte->libelle];
        $compte->update(['numero' => $donnees['numero'], 'libelle' => $donnees['libelle']]);

        ParametrageComptable::journaliser('compte', $compte->id, $compte->designation, 'MODIFICATION', $avant, [
            'numéro' => $compte->numero, 'libellé' => $compte->libelle,
        ]);

        return $this->retour('comptes')->with('success', "Compte {$compte->numero} mis à jour.");
    }

    public function compteBasculer(CompteComptable $compte)
    {
        $compte->statut = $compte->statut ? 0 : 1;
        $compte->save();

        ParametrageComptable::journaliser('compte', $compte->id, $compte->designation, $compte->statut ? 'ACTIVATION' : 'DESACTIVATION');

        return $this->retour('comptes')->with('success', "Compte {$compte->numero} " . ($compte->statut ? 'activé' : 'désactivé') . '.');
    }

    public function compteDestroy(CompteComptable $compte)
    {
        $emplois = $compte->emplois();
        if ($emplois) {
            $detail = collect($emplois)->map(fn ($nombre, $lieu) => "{$nombre} {$lieu}")->implode(', ');

            return $this->retour('comptes')->with('error',
                "Le compte {$compte->numero} est employé ({$detail}) : il ne se supprime pas. Désactivez-le, ou retirez-le d'abord de ces réglages.");
        }

        ParametrageComptable::journaliser('compte', $compte->id, $compte->designation, 'SUPPRESSION');
        $compte->delete();

        return $this->retour('comptes')->with('success', "Compte {$compte->numero} supprimé.");
    }

    // ================================================================== grandes familles

    public function famillesUpdate(Request $request)
    {
        $choix = (array) $request->input('compte', []);
        $comptes = CompteComptable::generaux()->actifs()->get()->keyBy('id');
        $modifiees = 0;

        foreach (Categorie::whereIn('id', array_keys($choix))->get() as $famille) {
            $nouveau = $choix[$famille->id] !== '' && $choix[$famille->id] !== null ? (int) $choix[$famille->id] : null;
            if ($nouveau !== null && !isset($comptes[$nouveau])) {
                continue;   // un compte inconnu, analytique ou désactivé ne s'enregistre pas
            }
            $ancien = $famille->compte_comptable_id ? (int) $famille->compte_comptable_id : null;
            if ($ancien === $nouveau) {
                continue;
            }

            $famille->compte_comptable_id = $nouveau;
            $famille->save();
            $modifiees++;

            ParametrageComptable::journaliser('famille', $famille->id, $famille->nom, 'MODIFICATION',
                ['compte général' => optional(CompteComptable::withTrashed()->find($ancien))->numero],
                ['compte général' => optional($comptes[$nouveau] ?? null)->numero]);
        }

        return $this->retour('familles')->with('success', $modifiees
            ? "{$modifiees} grande(s) famille(s) mise(s) à jour."
            : 'Aucun changement à enregistrer.');
    }

    // ================================================================== produits

    public function produitsUpdate(Request $request)
    {
        $familles    = (array) $request->input('famille', []);
        $analytiques = (array) $request->input('analytique', []);
        $ids = array_unique(array_merge(array_keys($familles), array_keys($analytiques)));

        $famillesVivantes = Categorie::where('statut', \Help::$STATUT_ACTIF)->pluck('nom', 'id');
        $comptes = CompteComptable::analytiques()->actifs()->get()->keyBy('id');
        $modifies = 0;

        foreach (Produit::whereIn('id', $ids)->get() as $produit) {
            $avant = $apres = [];

            if (array_key_exists($produit->id, $familles)) {
                $nouvelle = $familles[$produit->id] !== '' && $familles[$produit->id] !== null ? (int) $familles[$produit->id] : null;
                $ancienne = $produit->categorie_comptable_id ? (int) $produit->categorie_comptable_id : null;
                if ($nouvelle !== $ancienne && ($nouvelle === null || isset($famillesVivantes[$nouvelle]))) {
                    $avant['grande famille'] = $ancienne ? ($famillesVivantes[$ancienne] ?? ('n° ' . $ancienne)) : null;
                    $apres['grande famille'] = $nouvelle ? $famillesVivantes[$nouvelle] : null;
                    $produit->categorie_comptable_id = $nouvelle;
                }
            }

            if (array_key_exists($produit->id, $analytiques)) {
                $nouveau = $analytiques[$produit->id] !== '' && $analytiques[$produit->id] !== null ? (int) $analytiques[$produit->id] : null;
                $ancien  = $produit->compte_analytique_id ? (int) $produit->compte_analytique_id : null;
                if ($nouveau !== $ancien && ($nouveau === null || isset($comptes[$nouveau]))) {
                    $avant['compte analytique'] = optional(CompteComptable::withTrashed()->find($ancien))->numero;
                    $apres['compte analytique'] = optional($comptes[$nouveau] ?? null)->numero;
                    $produit->compte_analytique_id = $nouveau;
                }
            }

            if ($apres || $avant) {
                $produit->save();
                $modifies++;
                ParametrageComptable::journaliser('produit', $produit->id, $produit->nom, 'MODIFICATION', $avant, $apres);
            }
        }

        return $this->retour('produits')->with('success', $modifies
            ? "{$modifies} produit(s) mis à jour."
            : 'Aucun changement à enregistrer.');
    }

    /**
     * Les réglages « en un clic » des produits :
     *  - familles    : chaque produit sans famille reçoit sa première famille du catalogue ;
     *  - categorie   : tous les produits d'une catégorie du catalogue reçoivent cette famille ;
     *  - analytiques : chaque produit sans compte analytique en reçoit un, créé d'après sa référence.
     */
    public function produitsEnUnClic(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'categorie') {
            $famille = Categorie::where('statut', \Help::$STATUT_ACTIF)->find((int) $request->input('categorie_id'));
            if (!$famille) {
                return $this->retour('produits')->with('error', 'Choisissez une catégorie du catalogue.');
            }
            $remplacer = $request->boolean('remplacer');
            $ids = DB::table('categorie_produit')->whereNull('deleted_at')->where('categorie_id', $famille->id)->pluck('produit_id');
            $nombre = 0;
            foreach (Produit::whereIn('id', $ids)->where('statut', \Help::$STATUT_ACTIF)->get() as $produit) {
                if ((int) $produit->categorie_comptable_id === (int) $famille->id || ($produit->categorie_comptable_id && !$remplacer)) {
                    continue;
                }
                $avant = optional(Categorie::withTrashed()->find($produit->categorie_comptable_id))->nom;
                $produit->categorie_comptable_id = $famille->id;
                $produit->save();
                $nombre++;
                ParametrageComptable::journaliser('produit', $produit->id, $produit->nom, 'MODIFICATION',
                    ['grande famille' => $avant], ['grande famille' => $famille->nom]);
            }

            return $this->retour('produits')->with('success', "{$nombre} produit(s) rattaché(s) à la grande famille « {$famille->nom} ».");
        }

        if ($action === 'familles') {
            $nombre = 0;
            $sansProposition = 0;
            foreach (ParametrageComptable::produits()->whereNull('categorie_comptable_id') as $produit) {
                $proposee = ParametrageComptable::familleProposee($produit);
                if (!$proposee) {
                    $sansProposition++;
                    continue;
                }
                $produit->categorie_comptable_id = $proposee;
                $produit->save();
                $nombre++;
                ParametrageComptable::journaliser('produit', $produit->id, $produit->nom, 'MODIFICATION',
                    ['grande famille' => null], ['grande famille' => $produit->categories->firstWhere('id', $proposee)->nom]);
            }

            $message = "{$nombre} produit(s) rattaché(s) à leur première famille du catalogue.";
            if ($sansProposition) {
                $message .= " {$sansProposition} produit(s) ne sont rangés dans aucune catégorie : choisissez leur famille à la main.";
            }

            return $this->retour('produits')->with('success', $message);
        }

        if ($action === 'analytiques') {
            $pris = CompteComptable::withTrashed()->analytiques()->pluck('numero')->map(fn ($n) => strtoupper($n))->flip();
            $nombre = 0;

            foreach (Produit::where('statut', \Help::$STATUT_ACTIF)->whereNull('compte_analytique_id')->orderBy('id')->get() as $produit) {
                $numero = strtoupper(preg_replace('/[^A-Za-z0-9._-]/', '', (string) $produit->reference));
                $numero = ltrim(substr($numero, 0, 20), '._-');
                if ($numero === '' || isset($pris[$numero])) {
                    $numero = 'P' . str_pad((string) $produit->id, 5, '0', STR_PAD_LEFT);
                }
                if (isset($pris[$numero])) {
                    continue;   // ni la référence ni le numéro de repli ne sont libres : à régler à la main
                }
                $pris[$numero] = true;

                $compte = CompteComptable::create([
                    'nature' => CompteComptable::NATURE_ANALYTIQUE, 'numero' => $numero,
                    'libelle' => mb_substr($produit->nom, 0, 150), 'statut' => 1,
                ]);
                $produit->compte_analytique_id = $compte->id;
                $produit->save();
                $nombre++;

                ParametrageComptable::journaliser('compte', $compte->id, $compte->designation, 'CREATION', [], [
                    'nature' => CompteComptable::NATURES[$compte->nature], 'numéro' => $compte->numero, 'libellé' => $compte->libelle,
                ]);
                ParametrageComptable::journaliser('produit', $produit->id, $produit->nom, 'MODIFICATION',
                    ['compte analytique' => null], ['compte analytique' => $compte->numero]);
            }

            return $this->retour('produits')->with('success', "{$nombre} compte(s) analytique(s) créé(s) d'après la référence des produits.");
        }

        return $this->retour('produits')->with('error', 'Action inconnue.');
    }

    // ================================================================== rubriques

    public function rubriquesUpdate(Request $request)
    {
        $generaux    = (array) $request->input('compte', []);
        $analytiques = (array) $request->input('analytique', []);
        $comptesG = CompteComptable::generaux()->actifs()->get()->keyBy('id');
        $comptesA = CompteComptable::analytiques()->actifs()->get()->keyBy('id');
        $modifiees = 0;

        foreach (RubriqueComptable::toutes() as $rubrique) {
            $avant = [
                'compte général'    => optional($rubrique->compte)->numero,
                'compte analytique' => optional($rubrique->compteAnalytique)->numero,
            ];

            if (array_key_exists($rubrique->code, $generaux)) {
                $id = $generaux[$rubrique->code] !== '' && $generaux[$rubrique->code] !== null ? (int) $generaux[$rubrique->code] : null;
                if ($id === null || isset($comptesG[$id])) {
                    $rubrique->compte_comptable_id = $id;
                }
            }
            if ($rubrique->accepteUnAnalytique() && array_key_exists($rubrique->code, $analytiques)) {
                $id = $analytiques[$rubrique->code] !== '' && $analytiques[$rubrique->code] !== null ? (int) $analytiques[$rubrique->code] : null;
                if ($id === null || isset($comptesA[$id])) {
                    $rubrique->compte_analytique_id = $id;
                }
            }

            if ($rubrique->isDirty()) {
                $rubrique->save();
                $modifiees++;
                ParametrageComptable::journaliser('rubrique', $rubrique->id, $rubrique->libelle, 'MODIFICATION', $avant, [
                    'compte général'    => optional($comptesG[$rubrique->compte_comptable_id] ?? null)->numero,
                    'compte analytique' => optional($comptesA[$rubrique->compte_analytique_id] ?? null)->numero,
                ]);
            }
        }

        return $this->retour('rubriques')->with('success', $modifiees
            ? "{$modifiees} rubrique(s) mise(s) à jour."
            : 'Aucun changement à enregistrer.');
    }

    // ================================================================== comptes tiers

    public function tiersUpdate(Request $request)
    {
        $erreurs = [];
        $modifies = 0;

        foreach (self::TIERS as $objet => $classe) {
            $saisis = (array) $request->input($objet, []);
            if (!$saisis) {
                continue;
            }

            foreach ($classe::whereIn('id', array_keys($saisis))->get() as $tiers) {
                $nom = $this->nomDuTiers($objet, $tiers);
                $nouveau = strtoupper(trim((string) $saisis[$tiers->id]));
                $nouveau = $nouveau === '' ? null : $nouveau;
                $ancien = $tiers->compte_tiers ?: null;
                if ($nouveau === $ancien) {
                    continue;
                }

                if ($nouveau !== null) {
                    if ($erreur = ParametrageComptable::erreurDeCompteTiers($nouveau)) {
                        $erreurs[] = "{$nom} : {$erreur}";
                        continue;
                    }
                    if ($classe::where('compte_tiers', $nouveau)->where('id', '!=', $tiers->id)->exists()) {
                        $erreurs[] = "{$nom} : le compte tiers {$nouveau} est déjà attribué.";
                        continue;
                    }
                }

                $tiers->compte_tiers = $nouveau;
                $tiers->save();
                $modifies++;
                ParametrageComptable::journaliser($objet, $tiers->id, $nom, 'MODIFICATION', ['compte tiers' => $ancien], ['compte tiers' => $nouveau]);
            }
        }

        $reponse = $this->retour('tiers');
        if ($erreurs) {
            $reponse->withErrors(['tiers' => $erreurs]);
        }

        return $reponse->with($modifies || !$erreurs ? 'success' : 'error', $modifies
            ? "{$modifies} compte(s) tiers mis à jour."
            : ($erreurs ? 'Aucun compte tiers enregistré : voyez les erreurs.' : 'Aucun changement à enregistrer.'));
    }

    /**
     * Donne un compte tiers à chaque tiers qui n'en a pas : préfixe + numéro de fiche,
     * complété pour atteindre la longueur des comptes du plan.
     *
     * C'est la nomenclature du comptable : une racine, puis le rang sur les chiffres
     * qui restent — 4111 + 0007 donne 41110007, comme 46210007 dans sa balance Sage.
     */
    public function tiersGenerer(Request $request)
    {
        $donnees = $request->validate([
            'cible'   => ['required', Rule::in(array_keys(self::TIERS))],
            'prefixe' => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,12}$/'],
        ], ['prefixe.regex' => 'Le préfixe se compose de 1 à 12 lettres ou chiffres.'], ['prefixe' => 'préfixe']);

        $classe  = self::TIERS[$donnees['cible']];
        $prefixe = strtoupper($donnees['prefixe']);
        $nombre  = 0;

        $sansCompte = function ($q) {
            $q->whereNull('compte_tiers')->orWhere('compte_tiers', '');
        };
        // Le rang occupe ce qui reste après le préfixe ; jamais moins de trois chiffres,
        // pour qu'un préfixe long ne produise pas des comptes qui se ressemblent tous.
        $rang = max(3, ParametrageComptable::longueurCompte() - mb_strlen($prefixe));

        foreach ($classe::where($sansCompte)->orderBy('id')->get() as $tiers) {
            $compte = $prefixe . str_pad((string) $tiers->id, $rang, '0', STR_PAD_LEFT);
            if (ParametrageComptable::erreurDeCompteTiers($compte) || $classe::where('compte_tiers', $compte)->exists()) {
                continue;
            }
            $tiers->compte_tiers = $compte;
            $tiers->save();
            $nombre++;
            $nom = $this->nomDuTiers($donnees['cible'], $tiers);
            ParametrageComptable::journaliser($donnees['cible'], $tiers->id, $nom, 'MODIFICATION', ['compte tiers' => null], ['compte tiers' => $compte]);
        }

        return $this->retour('tiers')->with('success', "{$nombre} compte(s) tiers attribué(s) avec le préfixe {$prefixe}.");
    }

    // ================================================================== journaux et modes de règlement

    public function journalCreate()
    {
        return view('comptabilite.parametrage.journal', [
            'journal' => new JournalComptable(['type' => JournalComptable::TYPE_BANQUE, 'statut' => 1]),
            'mode'    => 'create',
            'comptes' => CompteComptable::generaux()->actifs()->orderBy('numero')->get(),
        ]);
    }

    public function journalEdit(JournalComptable $journal)
    {
        return view('comptabilite.parametrage.journal', [
            'journal' => $journal,
            'mode'    => 'edit',
            'comptes' => CompteComptable::generaux()->actifs()->orderBy('numero')->get(),
            'emplois' => $journal->emplois(),
        ]);
    }

    private function validerJournal(Request $request, ?JournalComptable $existant = null): array
    {
        $donnees = $request->validate([
            'code'                => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,6}$/'],
            'libelle'             => ['required', 'string', 'max:100'],
            'type'                => ['required', Rule::in(array_keys(JournalComptable::TYPES))],
            'compte_comptable_id' => ['nullable', 'integer'],
        ], ['code.regex' => 'Le code d\'un journal se compose de 1 à 6 lettres ou chiffres.'],
           ['code' => 'code', 'libelle' => 'libellé', 'type' => 'type', 'compte_comptable_id' => 'compte de trésorerie']);

        $donnees['code'] = strtoupper($donnees['code']);
        $donnees['libelle'] = trim($donnees['libelle']);

        $messages = [];
        if (JournalComptable::where('code', $donnees['code'])->when($existant, fn ($q) => $q->where('id', '!=', $existant->id))->exists()) {
            $messages['code'] = 'Ce code de journal existe déjà.';
        }
        if (!in_array($donnees['type'], JournalComptable::TYPES_DE_TRESORERIE, true)) {
            $donnees['compte_comptable_id'] = null;
        } elseif (!empty($donnees['compte_comptable_id'])
            && !CompteComptable::generaux()->actifs()->where('id', $donnees['compte_comptable_id'])->exists()) {
            $messages['compte_comptable_id'] = 'Choisissez un compte général actif.';
        }
        if ($messages) {
            throw \Illuminate\Validation\ValidationException::withMessages($messages);
        }

        $donnees['compte_comptable_id'] = $donnees['compte_comptable_id'] ?? null;

        return $donnees;
    }

    private function etatDuJournal(JournalComptable $journal): array
    {
        return [
            'code' => $journal->code, 'libellé' => $journal->libelle, 'type' => JournalComptable::TYPES[$journal->type] ?? $journal->type,
            'compte de trésorerie' => optional(CompteComptable::withTrashed()->find($journal->compte_comptable_id))->numero,
        ];
    }

    public function journalStore(Request $request)
    {
        $journal = JournalComptable::create($this->validerJournal($request) + ['statut' => 1]);
        ParametrageComptable::journaliser('journal', $journal->id, $journal->designation, 'CREATION', [], $this->etatDuJournal($journal));

        return $this->retour('journaux')->with('success', "Journal {$journal->code} créé.");
    }

    public function journalUpdate(Request $request, JournalComptable $journal)
    {
        $donnees = $this->validerJournal($request, $journal);
        $avant = $this->etatDuJournal($journal);
        $journal->update($donnees);
        ParametrageComptable::journaliser('journal', $journal->id, $journal->designation, 'MODIFICATION', $avant, $this->etatDuJournal($journal));

        return $this->retour('journaux')->with('success', "Journal {$journal->code} mis à jour.");
    }

    public function journalBasculer(JournalComptable $journal)
    {
        $journal->statut = $journal->statut ? 0 : 1;
        $journal->save();
        ParametrageComptable::journaliser('journal', $journal->id, $journal->designation, $journal->statut ? 'ACTIVATION' : 'DESACTIVATION');

        return $this->retour('journaux')->with('success', "Journal {$journal->code} " . ($journal->statut ? 'activé' : 'désactivé') . '.');
    }

    public function journalDestroy(JournalComptable $journal)
    {
        $emplois = $journal->emplois();
        if ($emplois) {
            $detail = collect($emplois)->map(fn ($nombre, $lieu) => "{$nombre} {$lieu}")->implode(', ');

            return $this->retour('journaux')->with('error',
                "Le journal {$journal->code} est employé ({$detail}) : il ne se supprime pas. Désactivez-le, ou détachez-le d'abord.");
        }

        ParametrageComptable::journaliser('journal', $journal->id, $journal->designation, 'SUPPRESSION');
        $journal->delete();

        return $this->retour('journaux')->with('success', "Journal {$journal->code} supprimé.");
    }

    public function modesUpdate(Request $request)
    {
        $choix = (array) $request->input('journal', []);
        $journaux = JournalComptable::actifs()->get()->filter->estDeTresorerie()->keyBy('id');
        $modifies = 0;

        foreach (ModePaiement::whereIn('id', array_keys($choix))->get() as $mode) {
            $nouveau = $choix[$mode->id] !== '' && $choix[$mode->id] !== null ? (int) $choix[$mode->id] : null;
            if ($nouveau !== null && !isset($journaux[$nouveau])) {
                continue;
            }
            $ancien = $mode->journal_comptable_id ? (int) $mode->journal_comptable_id : null;
            if ($ancien === $nouveau) {
                continue;
            }

            // Mise à jour ciblée : le modèle ModePaiement porte des règles propres qu'on ne réveille pas ici.
            DB::table('mode_paiement')->where('id', $mode->id)->update(['journal_comptable_id' => $nouveau]);
            $modifies++;
            ParametrageComptable::journaliser('mode', $mode->id, $mode->libelle, 'MODIFICATION',
                ['journal' => optional(JournalComptable::withTrashed()->find($ancien))->code],
                ['journal' => optional($journaux[$nouveau] ?? null)->code]);
        }

        return $this->retour('journaux')->with('success', $modifies
            ? "{$modifies} mode(s) de règlement mis à jour."
            : 'Aucun changement à enregistrer.');
    }

    // ================================================================== réglages

    public function reglagesUpdate(Request $request)
    {
        $donnees = $request->validate([
            'longueur_compte_comptable' => ['required', 'integer', 'between:' . ParametrageComptable::LONGUEUR_MIN . ',' . ParametrageComptable::LONGUEUR_MAX],
            'format_libelle_ecriture'   => ['nullable', 'string', 'max:190'],
            'journal_cautions_id'       => ['nullable', 'integer'],
        ], [], ['longueur_compte_comptable' => 'longueur des numéros de comptes', 'format_libelle_ecriture' => 'format du libellé']);

        $longueur = (int) $donnees['longueur_compte_comptable'];
        $nonConformes = CompteComptable::generaux()->whereRaw('CHAR_LENGTH(numero) <> ?', [$longueur])->count();
        if ($nonConformes) {
            return $this->retour('reglages')->withInput()->withErrors([
                'longueur_compte_comptable' => "{$nonConformes} compte(s) général(aux) du plan n'ont pas {$longueur} chiffres : corrigez-les d'abord, la longueur ne peut pas changer sous eux.",
            ]);
        }

        $journalCautions = !empty($donnees['journal_cautions_id'])
            ? JournalComptable::actifs()->get()->filter->estDeTresorerie()->firstWhere('id', (int) $donnees['journal_cautions_id'])
            : null;
        if (!empty($donnees['journal_cautions_id']) && !$journalCautions) {
            return $this->retour('reglages')->withInput()->withErrors(['journal_cautions_id' => 'Choisissez un journal de trésorerie actif pour les cautions.']);
        }

        $configuration = Configuration::first();
        $ancienJournalCautions = optional(JournalComptable::withTrashed()->find($configuration->journal_cautions_id))->code;
        $avant = [
            'journal des cautions' => $ancienJournalCautions,
            'longueur des numéros' => (string) ParametrageComptable::longueurCompte(),
            'format du libellé'    => ParametrageComptable::formatLibelle(),
        ];

        // Mise à jour ciblée : l'écran « Paramètres » a sa propre liste blanche, on n'y touche pas.
        DB::table('configuration')->where('id', $configuration->id)->update([
            'longueur_compte_comptable' => $longueur,
            'format_libelle_ecriture'   => trim((string) ($donnees['format_libelle_ecriture'] ?? '')) ?: null,
            'journal_cautions_id'       => $journalCautions?->id,
        ]);

        ParametrageComptable::journaliser('reglage', null, 'Réglages du plan de comptes', 'MODIFICATION', $avant, [
            'journal des cautions' => $journalCautions?->code,
            'longueur des numéros' => (string) ParametrageComptable::longueurCompte(),
            'format du libellé'    => ParametrageComptable::formatLibelle(),
        ]);

        return $this->retour('reglages')->with('success', 'Réglages enregistrés.');
    }
}
