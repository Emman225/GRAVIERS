<?php

namespace App\Http\Controllers;

use App\Models\StatutMetier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatutMetierController extends Controller
{
    public function index(Request $request)
    {
        $domaineFiltre = $request->query('domaine', '');
        $query = StatutMetier::query()->orderBy('domaine')->orderBy('ordre');
        if ($domaineFiltre && array_key_exists($domaineFiltre, StatutMetier::DOMAINES)) {
            $query->where('domaine', $domaineFiltre);
        }
        $statuts = $query->get();

        return view('admin.statutMetier.index', [
            'statuts'       => $statuts,
            'domaines'      => StatutMetier::DOMAINES,
            'domaineFiltre' => $domaineFiltre,
        ]);
    }

    public function create(Request $request)
    {
        $statut = new StatutMetier(['domaine' => $request->query('domaine', 'creance_terme')]);
        return view('admin.statutMetier.form', [
            'statut'        => $statut,
            'mode'          => 'create',
            'domaines'      => StatutMetier::DOMAINES,
            'badgeChoices'  => StatutMetier::BADGE_CHOICES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'domaine'     => ['required', 'string', Rule::in(array_keys(StatutMetier::DOMAINES))],
            'libelle'     => ['required', 'string', 'max:80'],
            'badge_class' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'ordre'       => ['nullable', 'integer', 'min:0'],
            'statut'      => ['nullable', 'boolean'],
        ]);

        // Unicité (domaine, libelle)
        $existing = StatutMetier::where('domaine', $validated['domaine'])
            ->where('libelle', $validated['libelle'])->exists();
        if ($existing) {
            return back()->withInput()->withErrors([
                'libelle' => 'Ce libellé existe déjà pour ce domaine.',
            ]);
        }

        $validated['ordre']  = $validated['ordre'] ?? 0;
        $validated['statut'] = $validated['statut'] ?? 1;

        StatutMetier::create($validated);

        return redirect()->route('show.statutMetier.index', ['domaine' => $validated['domaine']])
            ->with('success', "Statut « {$validated['libelle']} » créé avec succès.");
    }

    public function edit(StatutMetier $statutMetier)
    {
        return view('admin.statutMetier.form', [
            'statut'        => $statutMetier,
            'mode'          => 'edit',
            'domaines'      => StatutMetier::DOMAINES,
            'badgeChoices'  => StatutMetier::BADGE_CHOICES,
        ]);
    }

    public function update(Request $request, StatutMetier $statutMetier)
    {
        $validated = $request->validate([
            'domaine'     => ['required', 'string', Rule::in(array_keys(StatutMetier::DOMAINES))],
            'libelle'     => ['required', 'string', 'max:80'],
            'badge_class' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'ordre'       => ['nullable', 'integer', 'min:0'],
            'statut'      => ['nullable', 'boolean'],
        ]);

        $existing = StatutMetier::where('domaine', $validated['domaine'])
            ->where('libelle', $validated['libelle'])
            ->where('id', '!=', $statutMetier->id)
            ->exists();
        if ($existing) {
            return back()->withInput()->withErrors([
                'libelle' => 'Ce libellé existe déjà pour ce domaine.',
            ]);
        }

        $validated['ordre']  = $validated['ordre'] ?? 0;
        $validated['statut'] = $validated['statut'] ?? 1;

        $statutMetier->update($validated);

        return redirect()->route('show.statutMetier.index', ['domaine' => $statutMetier->domaine])
            ->with('success', "Statut « {$statutMetier->libelle} » mis à jour.");
    }

    public function destroy(StatutMetier $statutMetier)
    {
        $libelle = $statutMetier->libelle;
        $statutMetier->delete();

        return redirect()->route('show.statutMetier.index')
            ->with('success', "Statut « {$libelle} » supprimé.");
    }

    public function toggleStatut(StatutMetier $statutMetier)
    {
        $statutMetier->statut = ! $statutMetier->statut;
        $statutMetier->save();

        return redirect()->route('show.statutMetier.index', ['domaine' => $statutMetier->domaine])
            ->with('success', "Statut « {$statutMetier->libelle} » " . ($statutMetier->statut ? "activé" : "désactivé") . ".");
    }
}
