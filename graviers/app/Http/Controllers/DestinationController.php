<?php

namespace App\Http\Controllers;

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

        $region = new Ville;
        $region->nom = $request->nom;
        $region->region_id = $request->region_id;
        $region->user_id = Auth::user()->id;
        $region->save();

        return redirect()->route('dest.lesVilles')->with('success','Ville ajoutée');
    }

     public function modifierVille(Ville $ville){
        return view('gestionnaire.lesVilles',[
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
        return response()->json($resultat);

    }

}
