<?php

namespace App\Http\Controllers;

use App\Models\CoutLivraison;
use App\Models\UniteProduit;
use App\Models\Ville;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Administration de la GRILLE TARIFAIRE des demandes de livraison.
 *
 * La table cout_livraison fixe le prix d'un transport par tranche : une unité
 * de produit, une fourchette de quantité, une fourchette de distance, et un
 * forfait. Elle n'avait aucun écran : elle ne se modifiait qu'en base, ce qui
 * la rendait inexploitable par l'entreprise et expliquait qu'elle soit restée
 * en l'état — une seule unité tarifée sur neuf, rien au-delà de 100 km.
 *
 * Deux garde-fous, absents jusqu'ici :
 *   · aucune tranche ne peut en recouvrir une autre, sinon le tarif retenu
 *     dépend de l'ordre des lignes en base ;
 *   · les bornes sont contrôlées (min <= max, prix > 0).
 */
class GrilleTarifaireController extends Controller
{
    public function index()
    {
        $tarifs = CoutLivraison::with(['uniteProduit', 'ville'])
            ->orderBy('unite_produit_id')
            ->orderBy('ville_id')
            ->orderBy('unite_min')
            ->orderBy('distance_min_km')
            ->get();

        // Recouvrements DÉJÀ en base : on ne les corrige pas d'office — ce sont
        // des tarifs, seule l'entreprise sait lequel garder — mais on les
        // désigne, ligne par ligne.
        $enConflit = [];
        foreach ($tarifs as $t) {
            $conflits = CoutLivraison::tranchesEnConflit([
                'unite_produit_id' => $t->unite_produit_id,
                'ville_id'         => $t->ville_id,
                'unite_min'        => $t->unite_min,
                'unite_max'        => $t->unite_max,
                'distance_min_km'  => $t->distance_min_km,
                'distance_max_km'  => $t->distance_max_km,
            ], $t->id);

            if ($conflits->isNotEmpty()) {
                $enConflit[$t->id] = $conflits->pluck('id')->all();
            }
        }

        // Unités sans aucun tarif : une demande portant l'une d'elles est
        // refusée côté mobile, faute de prix. C'est la première chose à voir en
        // arrivant sur l'écran.
        $unites = UniteProduit::orderBy('libelle')->get();
        $unitesSansTarif = $unites->filter(
            fn (UniteProduit $u) => !CoutLivraison::where('unite_produit_id', $u->id)->exists()
        )->values();

        return view('admin.grilleTarifaire', [
            'tarifs'          => $tarifs,
            'enConflit'       => $enConflit,
            'unites'          => $unites,
            'unitesSansTarif' => $unitesSansTarif,
            'villes'          => Ville::orderBy('nom')->get(),
            'distanceMax'     => (float) (CoutLivraison::max('distance_max_km') ?? 0),
        ]);
    }

    public function store(Request $request)
    {
        $valeurs = $this->valider($request);

        $conflits = CoutLivraison::tranchesEnConflit($valeurs);
        if ($conflits->isNotEmpty()) {
            return back()->withInput()->with('erreur_grille', $this->messageConflit($conflits));
        }

        $tarif = CoutLivraison::create($valeurs);

        \Help::ecrireLog(
            'grilleTarifaire.store',
            'Grille tarifaire — nouvelle tranche',
            $this->descriptionTarif($tarif),
            Auth::id()
        );

        return back()->with('succes_grille', 'Tranche tarifaire ajoutée.');
    }

    public function update(Request $request, CoutLivraison $coutLivraison)
    {
        $valeurs = $this->valider($request);

        $conflits = CoutLivraison::tranchesEnConflit($valeurs, $coutLivraison->id);
        if ($conflits->isNotEmpty()) {
            return back()->withInput()->with('erreur_grille', $this->messageConflit($conflits));
        }

        $avant = $this->descriptionTarif($coutLivraison);
        $coutLivraison->update($valeurs);

        \Help::ecrireLog(
            'grilleTarifaire.update',
            'Grille tarifaire — tranche modifiée',
            $avant . '  ->  ' . $this->descriptionTarif($coutLivraison->fresh()),
            Auth::id()
        );

        return back()->with('succes_grille', 'Tranche tarifaire modifiée.');
    }

    public function destroy(CoutLivraison $coutLivraison)
    {
        $description = $this->descriptionTarif($coutLivraison);
        $coutLivraison->delete();

        \Help::ecrireLog(
            'grilleTarifaire.destroy',
            'Grille tarifaire — tranche supprimée',
            $description,
            Auth::id()
        );

        return back()->with('succes_grille', 'Tranche tarifaire supprimée.');
    }

    private function valider(Request $request): array
    {
        $valeurs = $request->validate([
            'unite_produit_id' => 'required|integer|exists:unite_produit,id',
            'ville_id'         => 'nullable|integer|exists:ville,id',
            'unite_min'        => 'required|numeric|min:0',
            'unite_max'        => 'required|numeric|min:0|gte:unite_min',
            'distance_min_km'  => 'required|numeric|min:0',
            'distance_max_km'  => 'required|numeric|min:0|gte:distance_min_km',
            'prix_km'          => 'required|numeric|min:1',
        ], [
            'unite_max.gte'       => 'La quantité maximale doit être supérieure ou égale à la minimale.',
            'distance_max_km.gte' => 'La distance maximale doit être supérieure ou égale à la minimale.',
            'prix_km.min'         => 'Le tarif doit être supérieur à zéro.',
        ]);

        // Une ville vide vaut « tarif générique » : on stocke NULL et non 0,
        // c'est ce que lireSurCle() attend.
        $valeurs['ville_id'] = $valeurs['ville_id'] ?: null;

        return $valeurs;
    }

    private function messageConflit($conflits): string
    {
        $liste = $conflits->map(fn (CoutLivraison $c) => $this->descriptionTarif($c))->implode(' ; ');

        return "Cette tranche en recouvre une ou plusieurs autres : {$liste}. "
            . "Deux tranches qui se croisent rendent le tarif indéterminé — la recherche prendrait "
            . "la première venue. Ajustez les bornes, ou modifiez la tranche existante.";
    }

    private function descriptionTarif(CoutLivraison $t): string
    {
        return sprintf(
            '#%d %s%s : qté %s-%s, %s-%s km = %s FCFA',
            $t->id,
            $t->uniteProduit?->libelle ?? ('unité ' . $t->unite_produit_id),
            $t->ville_id ? ' (' . ($t->ville?->nom ?? 'ville ' . $t->ville_id) . ')' : ' (générique)',
            rtrim(rtrim(number_format((float) $t->unite_min, 2, ',', ' '), '0'), ','),
            rtrim(rtrim(number_format((float) $t->unite_max, 2, ',', ' '), '0'), ','),
            rtrim(rtrim(number_format((float) $t->distance_min_km, 2, ',', ' '), '0'), ','),
            rtrim(rtrim(number_format((float) $t->distance_max_km, 2, ',', ' '), '0'), ','),
            number_format((float) $t->prix_km, 0, ',', ' ')
        );
    }
}
