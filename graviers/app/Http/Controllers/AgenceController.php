<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use Illuminate\Http\Request;

class AgenceController extends Controller
{
    public function index()
    {
        $agences = Agence::orderBy('code')->get();
        return view('admin.agence.index', compact('agences'));
    }

    public function create()
    {
        return view('admin.agence.form', [
            'agence' => new Agence(),
            'mode'   => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:30|unique:agence,code',
            'nom'         => 'required|string|max:150',
            'adresse'     => 'nullable|string|max:255',
            'telephone'   => 'nullable|string|max:50',
            'responsable' => 'nullable|string|max:150',
            'statut'      => 'nullable|integer',
        ]);
        $validated['statut'] = $validated['statut'] ?? 1;

        Agence::create($validated);

        return redirect()->route('show.agences.index')
            ->with('success', "Agence {$validated['code']} créée avec succès.");
    }

    public function edit(Agence $agence)
    {
        return view('admin.agence.form', [
            'agence' => $agence,
            'mode'   => 'edit',
        ]);
    }

    public function update(Request $request, Agence $agence)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:30|unique:agence,code,' . $agence->id,
            'nom'         => 'required|string|max:150',
            'adresse'     => 'nullable|string|max:255',
            'telephone'   => 'nullable|string|max:50',
            'responsable' => 'nullable|string|max:150',
            'statut'      => 'nullable|integer',
        ]);
        $validated['statut'] = $validated['statut'] ?? 1;

        $agence->update($validated);

        return redirect()->route('show.agences.index')
            ->with('success', "Agence {$agence->code} modifiée avec succès.");
    }

    public function destroy(Agence $agence)
    {
        // Vérification : pas de commande/paiement liés
        $cmdCount  = \App\Models\Commande::where('agence_id', $agence->id)->count();
        $payCount  = \App\Models\Paiement::where('agence_id', $agence->id)->count();
        if ($cmdCount > 0 || $payCount > 0) {
            return back()->with('error',
                "Impossible de supprimer {$agence->code} : {$cmdCount} commande(s) et {$payCount} paiement(s) y sont rattachés. Désactivez-la plutôt.");
        }

        $code = $agence->code;
        $agence->delete();

        return redirect()->route('show.agences.index')
            ->with('success', "Agence {$code} supprimée.");
    }

    public function toggleStatut(Agence $agence)
    {
        $agence->statut = ($agence->statut == 1) ? 2 : 1;
        $agence->save();
        $msg = $agence->statut == 1 ? 'activée' : 'désactivée';
        return redirect()->route('show.agences.index')
            ->with('success', "Agence {$agence->code} {$msg}.");
    }
}
