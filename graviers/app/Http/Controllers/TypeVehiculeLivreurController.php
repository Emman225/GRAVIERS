<?php

namespace App\Http\Controllers;

use App\Models\TypeVehiculeLivreur;
use Illuminate\Http\Request;

class TypeVehiculeLivreurController extends Controller
{
    public function index()
    {
        $types = TypeVehiculeLivreur::orderBy('capacite_tonnes')->get();
        return view('admin.typeVehiculeLivreur.index', compact('types'));
    }

    public function create()
    {
        return view('admin.typeVehiculeLivreur.form', [
            'type' => new TypeVehiculeLivreur(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'libelle'         => 'required|string|max:60|unique:type_vehicule_livreur,libelle',
            'capacite_tonnes' => 'nullable|numeric|min:0|max:99999',
            'description'     => 'nullable|string|max:255',
            'statut'          => 'nullable|boolean',
        ]);
        $validated['statut'] = $validated['statut'] ?? 1;

        TypeVehiculeLivreur::create($validated);

        return redirect()->route('show.typeVehiculeLivreur.index')
            ->with('success', "Type de véhicule « {$validated['libelle']} » créé avec succès.");
    }

    public function edit(TypeVehiculeLivreur $typeVehiculeLivreur)
    {
        return view('admin.typeVehiculeLivreur.form', [
            'type' => $typeVehiculeLivreur,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, TypeVehiculeLivreur $typeVehiculeLivreur)
    {
        $validated = $request->validate([
            'libelle'         => 'required|string|max:60|unique:type_vehicule_livreur,libelle,' . $typeVehiculeLivreur->id,
            'capacite_tonnes' => 'nullable|numeric|min:0|max:99999',
            'description'     => 'nullable|string|max:255',
            'statut'          => 'nullable|boolean',
        ]);
        $validated['statut'] = $validated['statut'] ?? 1;

        $typeVehiculeLivreur->update($validated);

        return redirect()->route('show.typeVehiculeLivreur.index')
            ->with('success', "Type de véhicule « {$typeVehiculeLivreur->libelle} » mis à jour.");
    }

    public function destroy(TypeVehiculeLivreur $typeVehiculeLivreur)
    {
        $nbLivreurs = $typeVehiculeLivreur->livreurs()->count();
        if ($nbLivreurs > 0) {
            return redirect()->route('show.typeVehiculeLivreur.index')
                ->with('error', "Impossible de supprimer : {$nbLivreurs} livreur(s) utilisent ce type de véhicule.");
        }

        $libelle = $typeVehiculeLivreur->libelle;
        $typeVehiculeLivreur->delete();

        return redirect()->route('show.typeVehiculeLivreur.index')
            ->with('success', "Type de véhicule « {$libelle} » supprimé.");
    }

    public function toggleStatut(TypeVehiculeLivreur $typeVehiculeLivreur)
    {
        $typeVehiculeLivreur->statut = ! $typeVehiculeLivreur->statut;
        $typeVehiculeLivreur->save();

        return redirect()->route('show.typeVehiculeLivreur.index')
            ->with('success', "Type de véhicule « {$typeVehiculeLivreur->libelle} » " . ($typeVehiculeLivreur->statut ? "activé" : "désactivé") . ".");
    }
}
