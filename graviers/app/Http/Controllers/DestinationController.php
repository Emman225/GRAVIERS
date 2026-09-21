<?php

namespace App\Http\Controllers;

use App\Models\Pays;
use App\Models\Ville;
use App\Models\Region;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DestinationController extends Controller
{
    //

    public function lesVilles(){
        return view('gestionnaire.lesVilles',[
            'lesRegions' => Region::orderByDesc('nom')->get(),
            'lesVilles' => Ville::orderByDesc('nom')->get(),
            'ville'=> new Ville,
        ]);
    }


    public function lesVillesValid(Request $request){
        // dd($request->all());
        $request->validate([
            'nom' => 'required',
            'region_id' => 'required',
        ],[
            'nom.required' => 'Le nom est requis',
            'region_id.required' => 'La région est requise',
        ]);

        $ville = new Ville;
        $ville->nom = $request->nom;
        $ville->region_id = $request->region_id;

        // DEUX ERREURS SE CUMULAIENT, ET AUCUNE VILLE NE POUVAIT ETRE CREEE.
        //
        //   · `user_id` etait ecrit alors que la table `ville` n'a PAS cette
        //     colonne. L'insertion partait avec un champ inconnu et MySQL la
        //     refusait : « Unknown column 'user_id' in 'field list' » — c'est
        //     l'erreur 500 constatee le 27/08/2026 sur /les-villes.
        //
        //   · `pays_id` n'etait jamais renseigne alors qu'il est OBLIGATOIRE et
        //     sans valeur par defaut. Une fois la premiere erreur levee, c'est
        //     celle-ci qui aurait pris le relais.
        //
        // Le formulaire ne propose pas de pays, et c'est voulu : l'entreprise
        // ne livre qu'en Cote d'Ivoire et la table n'en porte qu'un. On le lit
        // donc plutot que de l'ecrire en dur — un identifiant code en dur
        // deviendrait faux le jour ou la base serait remise a zero.
        $paysId = Pays::orderBy('id')->value('id');

        if (! $paysId) {
            return back()->withInput()->with(
                'erreurVille',
                "Aucun pays n'est enregistre : impossible de rattacher la ville. "
                . "Creez d'abord le pays avant d'ajouter des villes."
            );
        }

        $ville->pays_id = $paysId;
        $ville->save();

        return redirect()->route('dest.lesVilles')->with('success','Ville ajoutée');
    }

    /**
     * La page de creation. Le formulaire ne partage plus l'ecran de la liste.
     */
    public function nouvelleVille(){
        return view('gestionnaire.formVille',[
            'lesRegions' => Region::orderByDesc('nom')->get(),
            'lesVilles' => Ville::orderByDesc('nom')->get(),
            'ville' => new Ville,
        ]);
    }

     public function modifierVille(Ville $ville){
        return view('gestionnaire.formVille',[
            'regions' => Ville::orderBy('nom','asc')->get(),
            'lesRegions' => Region::orderByDesc('nom')->get(),
            'ville' => $ville,
            'lesVilles' => Ville::orderByDesc('nom')->get(),
        ]);
    }
    public function modifierVilleValid(Request $request, Ville $ville){
        // dd($request->all());
        $ville->nom = $request->nom;
        $ville->region_id = $request->region_id;
        $ville->update();

        return redirect()->route('dest.lesVilles')->with('success','Ville modifiée');
    }
    public function supprimerVille(Ville $ville){

        $ville->deleted_at = date('Y-m-d H:i:s');
        $ville->save();
        return redirect()->route('dest.lesVilles')->with('success','Ville supprimée');
    }

    public function villesDeRegion($region){
        $villes = Ville::orderBy('nom')->get();

        if($region != -1){
            $regionObj = Region::find($region);
            if ($regionObj) {
                $villes = $regionObj->villes;
            }
        }

        return response()->json([
            'villes'=>$villes->pluck('id','nom')
        ]);
    }

    public function regionVille($ville){
        $ville = Ville::find($ville);

        if (!$ville || !$ville->region) {
            return response()->json([
                'region' => null
            ]);
        }

        return response()->json([
            'region' => $ville->region->id
        ]);
    }

    public function calculCoutLivraison($long, $lat, $region_id){
        
        $resultat = Help::coutLivraison($long, $lat, $region_id);

        // TVA sur le transport (point 5), au taux du client connecté : le
        // total affiché en direct sur la page d'adresse doit être celui que
        // la page suivante confirmera.
        $clientConnecte = \Illuminate\Support\Facades\Auth::check()
            ? \App\Models\Client::where('user_id', \Illuminate\Support\Facades\Auth::id())->first()
            : null;
        $taux = $clientConnecte ? (float) \App\Models\Client::tva($clientConnecte) : 0.0;
        $resultat['tva_transport'] = Help::tvaSurTransport((float) ($resultat['cout_livraison'] ?? 0), $taux);

        return response()->json($resultat);

    }

}
