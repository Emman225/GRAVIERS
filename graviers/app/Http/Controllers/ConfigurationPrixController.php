<?php

namespace App\Http\Controllers;

use App\Models\PrixPersonnalise;
use Illuminate\Http\Request;

/**
 * NB : Depuis avril 2026, le contenu de la page « Configuration des prix »
 * a été fusionné dans la page « Paramètres » (onglet « Prix personnalisés »).
 * Ce contrôleur ne fait plus que :
 *  - rediriger /configuration-prix vers /parametre#tab-prix (compat. ancienne URL)
 *  - traiter les actions store/supprimer (POST/GET) en revenant sur /parametre.
 */
class ConfigurationPrixController extends Controller
{
    public function index()
    {
        // Page autonome retirée : on redirige vers l'onglet « Prix personnalisés »
        // de la page Paramètres.
        return redirect()->to(route('show.parametre') . '#tab-prix');
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:client,id',
            'produit_id' => 'required|exists:produit,id',
            'prix' => 'required|numeric|min:0',
        ]);

        PrixPersonnalise::updateOrCreate(
            [
                'client_id' => $request->client_id,
                'produit_id' => $request->produit_id,
            ],
            [
                'prix' => $request->prix,
            ]
        );

        return redirect()->to(route('show.parametre') . '#tab-prix')
            ->with('success', 'Prix personnalisé enregistré avec succès.');
    }

    public function supprimerProduit($id)
    {
        $prix = PrixPersonnalise::findOrFail($id);
        $prix->delete();

        return redirect()->to(route('show.parametre') . '#tab-prix')
            ->with('success', 'Prix personnalisé supprimé avec succès.');
    }

    public function supprimerClient($clientId)
    {
        PrixPersonnalise::where('client_id', $clientId)->delete();

        return redirect()->to(route('show.parametre') . '#tab-prix')
            ->with('success', 'Tous les prix personnalisés du client ont été supprimés.');
    }
}
