<?php

namespace App\Http\Controllers;

use App\Models\DeversementComptable;
use App\Services\Comptabilite\JournalDesEcritures;
use App\Services\Comptabilite\RapportsComptables;
use Illuminate\Http\Request;

/**
 * Les rapports comptables (module « Écritures comptables », phase 3b, lot 120).
 *
 * Tous en LECTURE SEULE : ils lisent les écritures produites par le module et
 * n'écrivent rien — ils ne peuvent donc ni modifier ni supprimer une écriture.
 * Tous se filtrent par plage de dates ou par mois, comme la transmission, et
 * s'exportent en Excel, Word et PDF par les boutons de la maison.
 */
class RapportsComptablesController extends Controller
{
    /** Les rapports, dans l'ordre de l'écran. */
    public const RAPPORTS = [
        'deversements' => ['Suivi des déversements', 'Ce qui a été transmis au logiciel comptable, ce qui attend encore, mois par mois.', 'md-send', true],
        'familles'     => ['État par grande famille', 'Déversé et non déversé par famille, et le cycle commandé → livré → facturé → déversé.', 'md-category', true],
        'soldes'       => ['Soldes des comptes', 'Ouverture, mouvements, clôture, et la part pas encore transmise.', 'md-account_balance', true],
        'grand-livre'  => ['Grand livre', 'Toutes les écritures d\'un compte, dans l\'ordre, avec son solde progressif. Général, des tiers, ou analytique.', 'md-menu_book', true],
        'balance'      => ['Balance', 'Un compte par ligne : totaux et solde. Générale, des tiers, ou analytique.', 'md-list', true],
        'consolidee'   => ['Balance consolidée', 'Regroupée par classe de comptes et par grande famille.', 'md-equalizer', true],
        'rapprochement' => ['Rapprochement FNE / écritures', 'Chaque facture certifiée a-t-elle bien son écriture ?', 'md-fact_check', false],
        'anomalies'    => ['Historique des anomalies', 'Anomalies ouvertes, corrigées, et le délai de correction.', 'md-error', false],
        'ventes'       => ['Chiffre d\'affaires', 'Par grande famille et par compte analytique, avec les comparaisons.', 'md-trending_up', false],
        'taxes'        => ['Taxes collectées', 'TVA facturée et AIRSI collecté, mois par mois.', 'md-receipt', false],
        'clients'      => ['Situation des clients', 'Facturé, encaissé, solde dû et ancienneté des créances.', 'md-people', false],
        'tresorerie'   => ['Trésorerie par mode de règlement', 'Entrées et sorties par journal et par mois.', 'md-payments', false],
        'journal-ventes' => ['Journal des ventes', 'La présentation classique, prête à imprimer.', 'md-print', false],
    ];

    private function periode(Request $request): array
    {
        return JournalDesEcritures::periode($request->all());
    }

    private function commun(Request $request, string $rapport): array
    {
        $periode = $this->periode($request);

        return [
            'periode'      => $periode,
            'rapport'      => $rapport,
            'titre'        => self::RAPPORTS[$rapport][0],
            'explication'  => self::RAPPORTS[$rapport][1],
            'moisProposes' => JournalDesEcritures::moisProposes(),
            'anneesProposees' => JournalDesEcritures::anneesProposees(),
            'rubriquesSansCompte' => RapportsComptables::rubriquesSansCompte(),
        ];
    }

    /** La page d'accueil : les treize rapports, avec ce que chacun montre. */
    public function index(Request $request)
    {
        $periode = $this->periode($request);

        return view('comptabilite.rapports.index', [
            'periode'      => $periode,
            'rapports'     => self::RAPPORTS,
            'moisProposes' => JournalDesEcritures::moisProposes(),
            'anneesProposees' => JournalDesEcritures::anneesProposees(),
            'deversements' => DeversementComptable::orderByDesc('id')->limit(5)->get(),
            'rubriquesSansCompte' => RapportsComptables::rubriquesSansCompte(),
        ]);
    }

    public function deversements(Request $request)
    {
        $c = $this->commun($request, 'deversements');

        return view('comptabilite.rapports.deversements', $c + [
            'lignes'       => RapportsComptables::deversementsParMois($c['periode']['du'], $c['periode']['au']),
            'transmissions' => DeversementComptable::with('user')
                ->whereDate('created_at', '>=', $c['periode']['du'])->whereDate('created_at', '<=', $c['periode']['au'])
                ->orderByDesc('id')->get(),
        ]);
    }

    public function familles(Request $request)
    {
        $c = $this->commun($request, 'familles');

        return view('comptabilite.rapports.familles', $c + [
            'lignes' => RapportsComptables::parFamilleEtMois($c['periode']['du'], $c['periode']['au']),
            'cycle'  => RapportsComptables::cycleParFamille($c['periode']['du'], $c['periode']['au']),
        ]);
    }

    public function soldes(Request $request)
    {
        $c = $this->commun($request, 'soldes');

        return view('comptabilite.rapports.soldes', $c + [
            'lignes' => RapportsComptables::soldesDesComptes($c['periode']['du'], $c['periode']['au']),
        ]);
    }

    public function grandLivre(Request $request)
    {
        $c = $this->commun($request, 'grand-livre');
        $sorte = in_array($request->query('sorte'), ['generale', 'tiers', 'analytique'], true) ? $request->query('sorte') : 'generale';
        $comptes = RapportsComptables::comptesMouvementes($c['periode']['du'], $c['periode']['au'], $sorte);

        // Changer de déclinaison change la liste : le compte demandé peut ne
        // plus exister, on retombe alors sur le premier de la nouvelle liste.
        $compte = (string) $request->query('compte', '');
        if ($compte === '' || !$comptes->contains('numero', $compte)) {
            $compte = (string) ($comptes->first()['numero'] ?? '');
        }

        return view('comptabilite.rapports.grandLivre', $c + [
            'sorte'   => $sorte,
            'comptes' => $comptes,
            'compte'  => $compte,
            'livre'   => $compte !== '' ? RapportsComptables::grandLivre($compte, $c['periode']['du'], $c['periode']['au'], $sorte) : null,
        ]);
    }

    public function balance(Request $request)
    {
        $c = $this->commun($request, 'balance');
        $sorte = in_array($request->query('sorte'), ['generale', 'tiers', 'analytique'], true) ? $request->query('sorte') : 'generale';

        return view('comptabilite.rapports.balance', $c + [
            'sorte'  => $sorte,
            'lignes' => RapportsComptables::balance($c['periode']['du'], $c['periode']['au'], $sorte),
        ]);
    }

    public function consolidee(Request $request)
    {
        $c = $this->commun($request, 'consolidee');

        return view('comptabilite.rapports.consolidee', $c + [
            'consolidee' => RapportsComptables::balanceConsolidee($c['periode']['du'], $c['periode']['au']),
        ]);
    }

    public function rapprochement(Request $request)
    {
        $c = $this->commun($request, 'rapprochement');

        return view('comptabilite.rapports.rapprochement', $c + [
            'lignes' => RapportsComptables::rapprochementFne($c['periode']['du'], $c['periode']['au']),
        ]);
    }

    public function anomalies(Request $request)
    {
        $c = $this->commun($request, 'anomalies');

        return view('comptabilite.rapports.anomalies', $c + [
            'lignes' => RapportsComptables::historiqueAnomalies($c['periode']['du'], $c['periode']['au']),
        ]);
    }

    public function ventes(Request $request)
    {
        $c = $this->commun($request, 'ventes');

        return view('comptabilite.rapports.ventes', $c + [
            'lignes' => RapportsComptables::chiffreDAffaires($c['periode']['du'], $c['periode']['au']),
        ]);
    }

    public function taxes(Request $request)
    {
        $c = $this->commun($request, 'taxes');

        return view('comptabilite.rapports.taxes', $c + [
            'lignes'  => RapportsComptables::taxesCollectees($c['periode']['du'], $c['periode']['au']),
            'detail'  => RapportsComptables::detailDesTaxes($c['periode']['du'], $c['periode']['au']),
        ]);
    }

    public function clients(Request $request)
    {
        $c = $this->commun($request, 'clients');

        return view('comptabilite.rapports.clients', $c + [
            'factures' => RapportsComptables::facturesDesClients($c['periode']['au']),
            'lignes' => RapportsComptables::situationDesClients($c['periode']['au']),
        ]);
    }

    public function tresorerie(Request $request)
    {
        $c = $this->commun($request, 'tresorerie');

        return view('comptabilite.rapports.tresorerie', $c + [
            'lignes' => RapportsComptables::tresorerie($c['periode']['du'], $c['periode']['au']),
            'detail' => RapportsComptables::detailDeTresorerie($c['periode']['du'], $c['periode']['au']),
        ]);
    }

    public function journalDesVentes(Request $request)
    {
        $c = $this->commun($request, 'journal-ventes');

        return view('comptabilite.rapports.journalDesVentes', $c + [
            'ecritures' => RapportsComptables::journalDesVentes($c['periode']['du'], $c['periode']['au']),
        ]);
    }
}
