<?php

namespace App\Http\Controllers;

use App\Models\CoutLivraison;
use App\Models\CoutLivraisonLivreur;
use App\Models\Livreur;
use App\Models\UniteProduit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LA GRILLE DE FACTURATION D'UN LIVREUR.
 *
 * Même écran, même structure et mêmes garde-fous que la grille client — c'est
 * précisément ce qui permet de lire la marge : chaque ligne affiche le tarif
 * client de la tranche correspondante, ce que touche le livreur, et ce qui
 * reste à DALAKOUN.
 *
 * Un prix livreur supérieur au tarif client est refusé : ce n'est plus une
 * marge, c'est une perte, et elle ne se découvre aujourd'hui qu'au moment de
 * régler la dette du livreur.
 */
class GrilleLivreurController extends Controller
{
    public function index(Livreur $livreur)
    {
        $tarifs = CoutLivraisonLivreur::with('uniteProduit')
            ->where('livreur_id', $livreur->id)
            ->orderBy('unite_produit_id')
            ->orderBy('unite_min')
            ->orderBy('distance_min_km')
            ->get();

        // Recouvrements déjà en base : on les désigne sans les corriger d'office.
        $enConflit = [];

        foreach ($tarifs as $t) {
            $conflits = CoutLivraisonLivreur::tranchesEnConflit([
                'livreur_id'       => $livreur->id,
                'unite_produit_id' => $t->unite_produit_id,
                'unite_min'        => $t->unite_min,
                'unite_max'        => $t->unite_max,
                'distance_min_km'  => $t->distance_min_km,
                'distance_max_km'  => $t->distance_max_km,
            ], $t->id);

            if ($conflits->isNotEmpty()) {
                $enConflit[$t->id] = $conflits->pluck('id')->all();
            }
        }

        return view('admin.grilleLivreur', [
            'livreur'     => $livreur->load('user'),
            'tarifs'      => $tarifs,
            'enConflit'   => $enConflit,
            'unites'      => UniteProduit::orderBy('libelle')->get(),
            // Combien de tranches client restent sans équivalent chez ce
            // livreur : sur celles-là, c'est son ancien tarif qui s'applique et
            // la marge n'est plus garantie.
            'trancheClient' => CoutLivraison::where(fn ($q) => $q->whereNull('ville_id')->orWhere('ville_id', '<=', 0))->count(),
        ]);
    }

    public function store(Request $request, Livreur $livreur)
    {
        $valeurs = $this->valider($request, $livreur);

        if (is_string($valeurs)) {
            return back()->withInput()->with('erreur_grille', $valeurs);
        }

        $conflits = CoutLivraisonLivreur::tranchesEnConflit($valeurs);

        if ($conflits->isNotEmpty()) {
            return back()->withInput()->with('erreur_grille', $this->messageConflit($conflits));
        }

        $tarif = CoutLivraisonLivreur::create($valeurs);

        \Help::ecrireLog(
            'grilleLivreur.store',
            'Grille livreur — nouvelle tranche',
            $this->description($livreur, $tarif),
            Auth::id()
        );

        return back()->with('succes_grille', 'Tranche ajoutée.');
    }

    public function update(Request $request, Livreur $livreur, CoutLivraisonLivreur $tranche)
    {
        $valeurs = $this->valider($request, $livreur);

        if (is_string($valeurs)) {
            return back()->withInput()->with('erreur_grille', $valeurs);
        }

        $conflits = CoutLivraisonLivreur::tranchesEnConflit($valeurs, $tranche->id);

        if ($conflits->isNotEmpty()) {
            return back()->withInput()->with('erreur_grille', $this->messageConflit($conflits));
        }

        $avant = $this->description($livreur, $tranche);
        $tranche->update($valeurs);

        \Help::ecrireLog(
            'grilleLivreur.update',
            'Grille livreur — tranche modifiée',
            $avant . '  ->  ' . $this->description($livreur, $tranche->fresh()),
            Auth::id()
        );

        return back()->with('succes_grille', 'Tranche modifiée.');
    }

    public function destroy(Livreur $livreur, CoutLivraisonLivreur $tranche)
    {
        $description = $this->description($livreur, $tranche);
        $tranche->delete();

        \Help::ecrireLog(
            'grilleLivreur.destroy',
            'Grille livreur — tranche supprimée',
            $description,
            Auth::id()
        );

        return back()->with('succes_grille',
            'Tranche supprimée. Sur ce cas, le livreur repasse sur son tarif habituel.');
    }

    /**
     * ATTRIBUER TOUTE LA GRILLE À UN LIVREUR, D'UN COUP.
     *
     * Un livreur nouvellement créé n'a AUCUNE tranche : ce qu'on lui doit se
     * calcule alors sur son ancien mode de tarification, sans marge garantie.
     * Les 96 tranches se saisissaient jusqu'ici une à une — autant dire jamais.
     *
     * La dérivation existait déjà, mais seulement en ligne de commande
     * (`livreur:grille --apply`), donc hors de portée d'ici. Elle est
     * maintenant dans un service, appelé par les deux.
     */
    public function preRemplir(Request $request)
    {
        $donnees = $request->validate([
            'livreur_id' => 'required|integer|exists:livreur,id',
            'part'       => 'required|numeric|min:1|max:99',
            'plancher'   => 'nullable|numeric|min:0',
            'remplacer'  => 'nullable|boolean',
        ], [
            'livreur_id.required' => 'Saisissez l’identifiant du livreur.',
            'livreur_id.exists'   => 'Aucun livreur ne porte cet identifiant.',
            'part.required'       => 'Indiquez la part revenant au livreur.',
            'part.min'            => 'La part du livreur doit être comprise entre 1 et 99 %.',
            'part.max'            => 'La part du livreur doit être comprise entre 1 et 99 %.',
        ]);

        $livreur = Livreur::with('user')->findOrFail($donnees['livreur_id']);
        $nom = $livreur->user->nom_prenoms ?? ('livreur n° ' . $livreur->id);

        // UNE GRILLE DÉJÀ REMPLIE NE S'ÉCRASE PAS SANS LE DIRE.
        //
        // Le remplissage efface d'abord tout : lancé par mégarde sur un livreur
        // dont les tranches ont été ajustées à la main, il les perdrait toutes,
        // et le livreur repasserait sans prévenir sur un autre tarif.
        $existantes = CoutLivraisonLivreur::where('livreur_id', $livreur->id)->count();

        if ($existantes > 0 && empty($donnees['remplacer'])) {
            return back()->withInput()->with('erreur_grille', sprintf(
                '%s a déjà %d tranche(s). Cochez « Remplacer la grille existante » '
                . 'pour les effacer et les refaire — sinon rien n’est touché.',
                $nom, $existantes
            ));
        }

        $tranchesClient = \App\Services\GrilleLivreurGenerateur::tranchesClient();

        if ($tranchesClient->isEmpty()) {
            return back()->withInput()->with('erreur_grille',
                'La grille tarifaire du client est vide : il n’y a rien à dériver. '
                . 'Renseignez-la d’abord.');
        }

        $plancher = ($donnees['plancher'] ?? null) === null || $donnees['plancher'] === ''
            ? null
            : (float) $donnees['plancher'];

        $bilan = \App\Services\GrilleLivreurGenerateur::remplir(
            $livreur, (float) $donnees['part'], $plancher);

        \Help::ecrireLog(
            'grilleLivreur.preRemplir',
            'Grille livreur — pré-remplissage',
            sprintf('%s : %d tranches dérivées à %s %% du tarif client%s. '
                . 'Le client paierait %s F au total, le livreur toucherait %s F.',
                $nom,
                $bilan['tranches'],
                rtrim(rtrim(number_format((float) $donnees['part'], 2, ',', ' '), '0'), ','),
                $plancher !== null
                    ? ', plancher ' . number_format($plancher, 0, ',', ' ') . ' F'
                    : '',
                number_format($bilan['total_client'], 0, ',', ' '),
                number_format($bilan['total_livreur'], 0, ',', ' ')
            ),
            Auth::id()
        );

        $message = sprintf(
            '%d tranches attribuées à %s (part %s %%, marge DALAKOUN %s %%).',
            $bilan['tranches'], $nom,
            rtrim(rtrim(number_format((float) $donnees['part'], 2, ',', ' '), '0'), ','),
            rtrim(rtrim(number_format(100 - (float) $donnees['part'], 2, ',', ' '), '0'), ',')
        );

        if ($bilan['relevees'] > 0) {
            $message .= sprintf(' %d tranche(s) relevée(s) au plancher.', $bilan['relevees']);
        }

        // Le dire plutôt que de le faire en silence : sur ces tranches le
        // plancher ne s’applique pas entièrement, et le livreur y touche moins
        // que le montant demandé — pour ne pas livrer à perte.
        if ($bilan['ecretees'] > 0) {
            $message .= sprintf(
                ' Attention : %d tranche(s) sont facturées au client MOINS que ce '
                . 'plancher ; le versement y est ramené au prix client.',
                $bilan['ecretees']
            );
        }

        return redirect()->route('show.grilleLivreur', $livreur)
            ->with('succes_grille', $message);
    }

    /**
     * Les bornes, puis la seule règle qui compte vraiment : on ne paie pas le
     * livreur plus cher que ce que le client paie.
     */
    private function valider(Request $request, Livreur $livreur)
    {
        $valeurs = $request->validate([
            'unite_produit_id' => 'required|integer|exists:unite_produit,id',
            'unite_min'        => 'required|numeric|min:0',
            'unite_max'        => 'required|numeric|min:0|gte:unite_min',
            'distance_min_km'  => 'required|numeric|min:0',
            'distance_max_km'  => 'required|numeric|min:0|gte:distance_min_km',
            'prix'             => 'required|numeric|min:0',
        ], [
            'unite_max.gte'       => 'La quantité maximale doit être supérieure ou égale à la minimale.',
            'distance_max_km.gte' => 'La distance maximale doit être supérieure ou égale à la minimale.',
            'prix.min'            => 'Le montant versé au livreur ne peut pas être négatif.',
        ]);

        $valeurs['livreur_id'] = $livreur->id;
        $valeurs['ville_id']   = null;

        $prixClient = $this->prixClient($valeurs);

        if ($prixClient !== null && (float) $valeurs['prix'] > $prixClient) {
            return sprintf(
                'Ce montant (%s F) dépasse ce que le client paie pour la même tranche (%s F) : '
                . 'la course ferait perdre %s F à DALAKOUN.',
                number_format((float) $valeurs['prix'], 0, ',', ' '),
                number_format($prixClient, 0, ',', ' '),
                number_format((float) $valeurs['prix'] - $prixClient, 0, ',', ' ')
            );
        }

        return $valeurs;
    }

    /** Le tarif client de la tranche qui couvre celle-ci. */
    private function prixClient(array $valeurs): ?float
    {
        $tranche = CoutLivraison::where('unite_produit_id', $valeurs['unite_produit_id'])
            ->where('unite_min', '<=', $valeurs['unite_min'])
            ->where('unite_max', '>=', $valeurs['unite_max'])
            ->where('distance_min_km', '<=', $valeurs['distance_min_km'])
            ->where('distance_max_km', '>=', $valeurs['distance_max_km'])
            ->where(fn ($q) => $q->whereNull('ville_id')->orWhere('ville_id', '<=', 0))
            ->first();

        return $tranche ? (float) $tranche->prix_km : null;
    }

    private function messageConflit($conflits): string
    {
        return 'Cette tranche en recouvre ' . $conflits->count() . ' autre(s) chez ce livreur : le tarif retenu '
            . 'deviendrait indéterminé. Ajustez les bornes, ou modifiez la tranche existante.';
    }

    private function description(Livreur $livreur, CoutLivraisonLivreur $t): string
    {
        return sprintf(
            'Livreur #%d — unité %d, quantité %s-%s, distance %s-%s km : %s F',
            $livreur->id,
            $t->unite_produit_id,
            $t->unite_min, $t->unite_max,
            $t->distance_min_km, $t->distance_max_km,
            number_format((float) $t->prix, 0, ',', ' ')
        );
    }
}
