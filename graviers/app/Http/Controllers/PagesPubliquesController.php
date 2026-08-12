<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Configuration;
use Illuminate\Support\Facades\DB;

/**
 * Pages institutionnelles du site public, appelées depuis le pied de page.
 *
 * Elles pointaient toutes vers « Site en construction ». Deux d'entre elles
 * existaient bien ailleurs mais n'étaient pas utilisables ici : le centre
 * d'aide est réservé au personnel connecté, et les formulaires « livreur » et
 * « fournisseur » sont ceux du back-office, où un administrateur crée le
 * compte — un visiteur y était renvoyé vers une page de connexion.
 */
class PagesPubliquesController extends Controller
{
    /**
     * Le gabarit client.main affiche les catégories dans le pied de page :
     * sans elles, toutes ces pages tomberaient sur « Undefined variable ».
     */
    private function donneesCommunes(): array
    {
        return [
            'categories' => Categorie::where('statut', 1)->get(),
        ];
    }

    public function livraisons()
    {
        return view('institutionnel.livraisons', array_merge($this->donneesCommunes(), [
            // Les conditionnements réellement proposés, tels qu'ils sont
            // paramétrés : la page ne doit pas annoncer une offre inexistante.
            'typesLivraison' => DB::table('type_livraison')->where('statut', 1)->get(),
        ]));
    }

    public function confidentialite()
    {
        $config = Configuration::first();

        return view('institutionnel.confidentialite', array_merge($this->donneesCommunes(), [
            // Repli sur la dénomination sociale connue : le paramétrage de
            // l'entreprise peut être incomplet, et cette page ne doit pas
            // afficher un responsable de traitement vide.
            'raisonSociale' => $config && !empty(trim((string) $config->raison_sociale))
                ? $config->raison_sociale
                : 'DALAKOUN SARL',
        ]));
    }

    public function centreAide()
    {
        return view('institutionnel.centre-aide', $this->donneesCommunes());
    }

    public function devenirLivreur()
    {
        return view('institutionnel.devenir-livreur', $this->donneesCommunes());
    }

    public function devenirFournisseur()
    {
        return view('institutionnel.devenir-fournisseur', $this->donneesCommunes());
    }
}
