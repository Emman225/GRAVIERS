<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Le journal des opérations du back-office.
 *
 * Depuis le 29/08/2026, il ne s'affiche plus sur un écran à lui : il occupe
 * l'onglet « Audit » de la page « Paramètre ». Cette adresse subsiste pour les
 * favoris déjà posés — et parce qu'elle porte le middleware
 * `admin.seulement`, qui refuse les autres profils.
 *
 * Les filtres sont reportés tels quels : arriver sur le journal sans ses
 * critères ferait perdre la recherche en cours.
 *
 * La lecture du journal elle-même vit dans le modèle `Audit` : deux écrans en
 * ont besoin, et deux copies finissent toujours par diverger.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $parametres = array_filter(
            $request->only(['user_id', 'action', 'du', 'au', 'recherche']),
            fn ($v) => $v !== null && $v !== ''
        );

        $parametres['onglet'] = 'audit';

        return redirect()->route('show.parametre', $parametres);
    }
}
