<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeversementComptable;
use App\Models\EcritureComptable;
use App\Services\Comptabilite\Deversement;
use App\Services\Comptabilite\FormatSage;
use App\Services\Comptabilite\JournalDesEcritures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ReponseFacade;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * L'API DU MODULE « ÉCRITURES COMPTABLES » (phase 4, lot 121, 22/09/2026).
 *
 * Réservée à l'administrateur du site DALAKOUN — les écritures sont produites
 * par et pour DALAKOUN, jamais pour la comptabilité des clients ou des
 * fournisseurs de la plateforme (réponse du responsable, question 5 du
 * cahier initial). Authentification par jeton Sanctum, aptitude
 * « comptabilite:lecture » pour tout, « comptabilite:ecriture » en plus pour
 * le seul point d'écriture (POST /deversements).
 *
 * Identifiant stable : chaque écriture se référence par son `identifiant`
 * (FAC-…, ENC-…), jamais par son id interne — c'est lui qui reste vrai d'un
 * export à l'autre et qui évite les doublons.
 */
class EcrituresComptablesApiController extends Controller
{
    private const PAR_PAGE_MAX = 200;

    /** GET /api/comptabilite/ecritures — liste par période, filtrée, paginée. */
    public function index(Request $request)
    {
        $periode = $this->periode($request->all());
        $parPage = min(self::PAR_PAGE_MAX, max(1, (int) $request->query('par_page', 50)));

        $requete = EcritureComptable::with('lignes')
            ->whereBetween('date_ecriture', [$periode['du']->toDateString(), $periode['au']->toDateString()])
            ->when($request->filled('etat') && in_array($request->query('etat'), array_keys(EcritureComptable::ETATS), true),
                fn ($q) => $q->where('etat', $request->query('etat')))
            ->when($request->filled('origine') && in_array($request->query('origine'), array_keys(EcritureComptable::ORIGINES), true),
                fn ($q) => $q->where('origine', $request->query('origine')))
            ->when($request->filled('journal'), fn ($q) => $q->where('journal_code', $request->query('journal')))
            ->when($request->filled('service'), fn ($q) => $q->where('service', $request->query('service')))
            ->orderBy('date_ecriture')->orderBy('id');

        $page = $requete->paginate($parPage)->appends($request->query());

        return response()->json([
            'periode' => ['mode' => $periode['mode'], 'du' => $periode['du']->toDateString(), 'au' => $periode['au']->toDateString()],
            'donnees' => FormatSage::json($page->getCollection()),
            'pagination' => [
                'page' => $page->currentPage(), 'par_page' => $page->perPage(),
                'total' => $page->total(), 'dernieres_page' => $page->lastPage(),
            ],
            'liens' => ['suivant' => $page->nextPageUrl(), 'precedent' => $page->previousPageUrl()],
        ]);
    }

    /** GET /api/comptabilite/ecritures/{identifiant} — le détail d'une écriture, par son identifiant stable. */
    public function show(EcritureComptable $ecriture)
    {
        $ecriture->load('lignes');

        return response()->json(['donnees' => FormatSage::json(collect([$ecriture]))[0]]);
    }

    /**
     * GET /api/comptabilite/ecritures/export — la période entière, sans pagination,
     * en fichier CSV/Sage ou en JSON complet. Lecture seule : rien ne change d'état.
     */
    public function export(Request $request)
    {
        $format = $this->format($request);
        $periode = $this->periode($request->all());
        $ecritures = JournalDesEcritures::ecritures($periode['du'], $periode['au'], [
            'etat' => $request->filled('etat') ? $request->query('etat') : null,
        ]);

        if ($format === 'SAGE') {
            return Excel::download(new \App\Exports\EcrituresComptablesExport($ecritures), Deversement::nomDuFichier($periode['du'], $periode['au'], $periode['mode'], 'SAGE'));
        }
        if ($format === 'CSV') {
            return ReponseFacade::streamDownload(fn () => print(FormatSage::csv($ecritures)),
                Deversement::nomDuFichier($periode['du'], $periode['au'], $periode['mode'], 'CSV'), ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->json(['periode' => ['du' => $periode['du']->toDateString(), 'au' => $periode['au']->toDateString()], 'donnees' => FormatSage::json($ecritures)]);
    }

    /** GET /api/comptabilite/deversements — les transmissions déjà enregistrées. */
    public function deversements(Request $request)
    {
        $parPage = min(self::PAR_PAGE_MAX, max(1, (int) $request->query('par_page', 20)));
        $page = DeversementComptable::orderByDesc('id')->paginate($parPage)->appends($request->query());

        return response()->json([
            'donnees' => $page->getCollection()->map(fn (DeversementComptable $d) => $this->deversementEnJson($d))->all(),
            'pagination' => ['page' => $page->currentPage(), 'par_page' => $page->perPage(), 'total' => $page->total(), 'dernieres_page' => $page->lastPage()],
        ]);
    }

    /** GET /api/comptabilite/deversements/{numero} — le détail d'un déversement, avec ses écritures. */
    public function deversementDetail(DeversementComptable $deversement)
    {
        $ecritures = EcritureComptable::with('lignes')->where('deversement_id', $deversement->id)->orderBy('date_ecriture')->orderBy('id')->get();

        return response()->json(['donnees' => $this->deversementEnJson($deversement) + ['ecritures' => FormatSage::json($ecritures)]]);
    }

    /**
     * POST /api/comptabilite/deversements — LE SEUL POINT D'ÉCRITURE DE L'API.
     *
     * Confirme la réception d'un lot par le logiciel comptable : les écritures
     * de la période passent à « exportée » (identifiant stable, jamais
     * réexportées ensuite) — c'est cet appel qui les y fait passer, pas la
     * simple consultation. Réutilise exactement les règles de la transmission
     * web (Deversement::transmettre) : une période en anomalie ne part pas.
     */
    public function accuser(Request $request)
    {
        $donnees = $request->validate([
            'mode_periode' => ['required', Rule::in([JournalDesEcritures::PAR_DATES, JournalDesEcritures::PAR_MOIS])],
        ], [], ['mode_periode' => 'mode de période']);
        $format = $this->format($request);

        $periode = $this->periode(array_merge($request->all(), ['mode_periode' => $donnees['mode_periode']]));
        $resultat = Deversement::transmettre($periode['du'], $periode['au'], $periode['mode'], $format);

        if (!$resultat['success']) {
            return response()->json(['message' => $resultat['message']], 422);
        }

        Deversement::accuserReception($resultat['deversement']);
        $deversement = $resultat['deversement']->fresh();

        return response()->json([
            'message' => $resultat['message'] . ' Accusé de réception enregistré.',
            'donnees' => $this->deversementEnJson($deversement) + ['ecritures' => FormatSage::json($resultat['ecritures'])],
        ], 201);
    }

    private function periode(array $entrees): array
    {
        return JournalDesEcritures::periode($entrees);
    }

    /** « json », « CSV », « Sage »… tout est ramené en majuscules — un format en API ne devrait pas dépendre de la casse tapée. */
    private function format(Request $request): string
    {
        $format = strtoupper((string) $request->query('format', $request->input('format', 'JSON')));
        if (!array_key_exists($format, DeversementComptable::FORMATS)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'format' => 'Le format doit être l\'un de : ' . implode(', ', array_keys(DeversementComptable::FORMATS)) . '.',
            ]);
        }

        return $format;
    }

    private function deversementEnJson(DeversementComptable $d): array
    {
        return [
            'numero' => $d->numero, 'mode_periode' => $d->mode_periode,
            'du' => $d->du->toDateString(), 'au' => $d->au->toDateString(), 'format' => $d->format,
            'nombre_ecritures' => $d->nombre_ecritures, 'nombre_lignes' => $d->nombre_lignes,
            'total_debit' => round((float) $d->total_debit, 2), 'total_credit' => round((float) $d->total_credit, 2),
            'etat' => $d->etat, 'transmis_le' => $d->created_at?->toIso8601String(), 'accuse_le' => $d->accuse_le?->toIso8601String(),
        ];
    }
}
