<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Jetons de l'API comptable et documentation pour l'intégrateur (module
 * « Écritures comptables », phase 4, lot 121, 22/09/2026).
 *
 * Réservé aux administrateurs (route `admin.seulement`) : un jeton donne accès
 * aux écritures de DALAKOUN, jamais à celles d'un client ou d'un fournisseur
 * de la plateforme.
 */
class JetonsApiComptableController extends Controller
{
    public const APTITUDE_LECTURE  = 'comptabilite:lecture';
    public const APTITUDE_ECRITURE = 'comptabilite:ecriture';

    public function index()
    {
        $jetons = PersonalAccessToken::where('tokenable_type', User::class)
            ->whereJsonContains('abilities', self::APTITUDE_LECTURE)
            ->orderByDesc('id')->get();

        return view('comptabilite.jetonsApi.index', [
            'jetons' => $jetons,
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'nom'      => ['required', 'string', 'max:100'],
            'ecriture' => ['nullable', 'boolean'],
        ], [], ['nom' => 'nom du jeton']);

        $aptitudes = [self::APTITUDE_LECTURE];
        if ($request->boolean('ecriture')) {
            $aptitudes[] = self::APTITUDE_ECRITURE;
        }

        $utilisateur = Auth::user();
        $resultat = $utilisateur->createToken(mb_substr($donnees['nom'], 0, 100), $aptitudes);

        // Note lisible : qui l'a créé, pour cette liste — le champ ajouté par la migration du lot 121.
        DB::table('personal_access_tokens')->where('id', $resultat->accessToken->id)->update([
            'cree_par_id' => $utilisateur->id,
            'note'        => $request->boolean('ecriture') ? 'Lecture + accusé de réception' : 'Lecture seule',
        ]);

        return redirect()->route('show.comptabilite.jetonsApi.index')
            ->with('jetonEnClair', $resultat->plainTextToken)
            ->with('success', "Jeton « {$donnees['nom']} » créé. Copiez-le maintenant : il ne sera plus jamais affiché.");
    }

    public function revoquer(PersonalAccessToken $jeton)
    {
        $nom = $jeton->name;
        $jeton->delete();

        return redirect()->route('show.comptabilite.jetonsApi.index')->with('success', "Jeton « {$nom} » révoqué : il ne fonctionne plus.");
    }
}
