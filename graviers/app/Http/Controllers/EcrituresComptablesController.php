<?php

namespace App\Http\Controllers;

use App\Exports\EcrituresComptablesExport;
use App\Models\DeversementComptable;
use App\Models\EcritureComptable;
use App\Models\JournalComptable;
use App\Services\Comptabilite\Deversement;
use App\Services\Comptabilite\FormatSage;
use App\Services\Comptabilite\JournalDesEcritures;
use App\Services\Comptabilite\MoteurEcritures;
use App\Services\Comptabilite\MoteurTresorerie;
use App\Services\Comptabilite\RapportsComptables;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Le journal des écritures, le rapport d'anomalies et la transmission au
 * logiciel comptable (module « Écritures comptables », phase 3, lot 119).
 * Réservé aux administrateurs, comme le paramétrage.
 */
class EcrituresComptablesController extends Controller
{
    private function periode(Request $request): array
    {
        return JournalDesEcritures::periode($request->all());
    }

    private function filtres(Request $request): array
    {
        return [
            'etat'        => in_array($request->query('etat'), array_keys(EcritureComptable::ETATS), true) ? $request->query('etat') : null,
            'journal'     => (int) $request->query('journal') ?: null,
            'origine'     => in_array($request->query('origine'), array_keys(EcritureComptable::ORIGINES), true) ? $request->query('origine') : null,
            'deversement' => (int) $request->query('deversement') ?: null,
        ];
    }

    /** Le journal des écritures de la période. */
    public function index(Request $request)
    {
        $periode = $this->periode($request);
        $filtres = $this->filtres($request);
        $deversements = DeversementComptable::with('user')->orderByDesc('id')->limit(50)->get();

        return view('comptabilite.ecritures.index', [
            'periode'     => $periode,
            'filtres'     => $filtres,
            'ecritures'   => JournalDesEcritures::ecritures($periode['du'], $periode['au'], $filtres),
            'resume'      => JournalDesEcritures::resume($periode['du'], $periode['au']),
            'anomalies'   => JournalDesEcritures::anomalies($periode['du'], $periode['au']),
            'apercu'      => Deversement::apercu($periode['du'], $periode['au']),
            'journaux'    => JournalComptable::orderBy('code')->get(),
            'moisProposes' => JournalDesEcritures::moisProposes(),
            'deversements' => $deversements,
            'resumeDeversements' => RapportsComptables::resumeDesDeversements($deversements->pluck('id')->all()),
        ]);
    }

    /** Le détail d'une écriture, ligne par ligne. */
    /**
     * Les factures emportées par un déversement (demande du 25/09/2026) : un
     * envoi n'a pas UN numéro de facture, il en couvre autant que la période
     * en portait. Le suivi en donne le nombre, cet écran en donne la liste.
     */
    public function facturesDuDeversement(DeversementComptable $deversement)
    {
        return view('comptabilite.ecritures.deversement', [
            'deversement' => $deversement->load('user'),
            'ecritures'   => EcritureComptable::where('deversement_id', $deversement->id)
                ->orderBy('date_ecriture')->orderBy('id')->get(),
        ]);
    }

    public function detail(EcritureComptable $ecriture)
    {
        $ecriture->load(['lignes.compte', 'lignes.compteAnalytique', 'lignes.famille', 'lignes.produit', 'journal', 'client', 'anomalies', 'annuleePar', 'annulationDe']);

        return view('comptabilite.ecritures.detail', [
            'ecriture'    => $ecriture,
            'deversement' => $ecriture->deversement_id ? DeversementComptable::find($ecriture->deversement_id) : null,
            'soeurs'      => EcritureComptable::where('source_type', $ecriture->source_type)->where('source_id', $ecriture->source_id)
                ->where('id', '!=', $ecriture->id)->orderBy('id')->get(),
        ]);
    }

    /** Le rapport d'anomalies : ce qui empêche d'exporter, et où corriger. */
    public function anomalies(Request $request)
    {
        $periode = $this->periode($request);
        $ouvertes = $request->query('etat') === 'corrigees' ? 'corrigees' : 'ouvertes';

        $requete = \App\Models\AnomalieComptable::with(['ecriture', 'facture'])
            ->whereIn('ecriture_comptable_id', EcritureComptable::whereBetween('date_ecriture', [$periode['du']->toDateString(), $periode['au']->toDateString()])->pluck('id'))
            ->when($ouvertes === 'ouvertes', fn ($q) => $q->whereNull('resolue_le'))
            ->when($ouvertes === 'corrigees', fn ($q) => $q->whereNotNull('resolue_le'));

        return view('comptabilite.ecritures.anomalies', [
            'periode'      => $periode,
            'etat'         => $ouvertes,
            'anomalies'    => $requete->orderByDesc('id')->get(),
            'moisProposes' => JournalDesEcritures::moisProposes(),
        ]);
    }

    /** Reprend les écritures en anomalie, après une correction du paramétrage. */
    public function reprendre(Request $request)
    {
        $factures   = MoteurEcritures::reprendreLesAnomalies();
        $tresorerie = MoteurTresorerie::reprendreLesAnomalies();
        $reprises = $factures['reprises'] + $tresorerie['reprises'];
        $reglees  = $factures['reglees'] + $tresorerie['reglees'];

        return back()->with($reglees ? 'success' : 'warning', $reprises === 0
            ? 'Aucune écriture en anomalie à reprendre.'
            : "{$reprises} écriture(s) reprise(s), {$reglees} réglée(s)." . ($reprises > $reglees ? ' Les autres attendent encore une correction du paramétrage.' : ''));
    }

    /** L'aperçu de ce qui partirait, avant de transmettre. */
    public function apercu(Request $request)
    {
        $periode = $this->periode($request);
        $apercu = Deversement::apercu($periode['du'], $periode['au']);

        return response()->json([
            'nombre'    => $apercu['nombre'],
            'lignes'    => $apercu['lignes'],
            'debit'     => $apercu['debit'],
            'credit'    => $apercu['credit'],
            'anomalies' => $apercu['anomalies']->count(),
            'possible'  => $apercu['possible'],
        ]);
    }

    /**
     * La transmission : le fichier part, et les écritures passent à « exportée ».
     * Un simple téléchargement de contrôle (« Voir le fichier ») n'enregistre
     * rien : c'est `telecharger`.
     */
    public function transmettre(Request $request)
    {
        $donnees = $request->validate([
            'format' => ['required', Rule::in(array_keys(DeversementComptable::FORMATS))],
        ], [], ['format' => 'format']);

        $periode = $this->periode($request);
        $resultat = Deversement::transmettre($periode['du'], $periode['au'], $periode['mode'], $donnees['format']);

        if (!$resultat['success']) {
            return back()->with('error', $resultat['message']);
        }

        // Le fichier est produit APRÈS l'enregistrement : le déversement fait foi,
        // et le fichier se retélécharge à l'identique depuis le journal des déversements.
        session()->flash('success', $resultat['message']);

        return $this->fichier($resultat['ecritures'], $donnees['format'], $resultat['deversement']->fichier);
    }

    /** Le fichier d'un déversement déjà fait, tel qu'il est parti. */
    public function telechargerDeversement(DeversementComptable $deversement, string $format = null)
    {
        $format = $format && array_key_exists(strtoupper($format), DeversementComptable::FORMATS) ? strtoupper($format) : $deversement->format;
        $ecritures = EcritureComptable::with('lignes')->where('deversement_id', $deversement->id)->orderBy('date_ecriture')->orderBy('id')->get();

        if ($ecritures->isEmpty()) {
            return back()->with('error', 'Ce déversement ne porte plus d\'écriture : il a été rejeté, et elles sont reparties à « à exporter ».');
        }

        return $this->fichier($ecritures, $format, Deversement::nomDuFichier($deversement->du, $deversement->au, $deversement->mode_periode, $format));
    }

    /** Un téléchargement de contrôle : rien n'est enregistré, rien ne change d'état. */
    public function telecharger(Request $request, string $format)
    {
        $format = strtoupper($format);
        abort_unless(array_key_exists($format, DeversementComptable::FORMATS), 404);

        $periode = $this->periode($request);
        $ecritures = JournalDesEcritures::ecritures($periode['du'], $periode['au'], $this->filtres($request));

        if ($ecritures->isEmpty()) {
            return back()->with('error', 'Aucune écriture sur cette période.');
        }

        return $this->fichier($ecritures, $format, 'controle-' . Deversement::nomDuFichier($periode['du'], $periode['au'], $periode['mode'], $format));
    }

    private function fichier($ecritures, string $format, string $nom)
    {
        if ($format === 'SAGE') {
            return Excel::download(new EcrituresComptablesExport($ecritures), $nom);
        }
        if ($format === 'JSON') {
            return response()->streamDownload(
                fn () => print(json_encode(FormatSage::json($ecritures), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                $nom, ['Content-Type' => 'application/json']
            );
        }

        return response()->streamDownload(fn () => print(FormatSage::csv($ecritures)), $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function accuser(DeversementComptable $deversement)
    {
        Deversement::accuserReception($deversement);

        return back()->with('success', 'Déversement n° ' . $deversement->numero . ' : accusé de réception enregistré.');
    }

    public function rejeter(Request $request, DeversementComptable $deversement)
    {
        $donnees = $request->validate([
            'motif_rejet' => ['required', 'string', 'max:255'],
        ], [], ['motif_rejet' => 'motif du rejet']);

        Deversement::rejeter($deversement, $donnees['motif_rejet']);

        return back()->with('success', 'Déversement n° ' . $deversement->numero . ' rejeté : ses écritures sont de nouveau à transmettre.');
    }
}
