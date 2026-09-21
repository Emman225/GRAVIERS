<?php

namespace App\Traits;

use App\Models\DemandePaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * LA PREUVE ET LA FINALISATION D'UN RÈGLEMENT DE DETTE (point 20, 09/09/2026).
 *
 * Même circuit que sur les demandes de paiement (UserController) : après la 2e
 * validation, l'agent qui fait le virement joint la preuve (PDF, JPG, PNG) ;
 * une fois jointe, il finalise : « Effectuée », et le partenaire le voit.
 * Les trois contrôleurs de dettes l'utilisent avec leur propre modèle.
 */
trait PreuveDeReglementPartenaire
{
    protected function joindrePreuveReglement($paiement, Request $request)
    {
        if (!$paiement) {
            return back()->with('error', 'Règlement introuvable.');
        }
        $request->validate([
            'preuve' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'preuve.required' => 'Joignez la preuve du paiement (PDF, JPG ou PNG, 5 Mo maximum).',
            'preuve.mimes'    => 'La preuve doit être un PDF ou une image (JPG, PNG).',
            'preuve.max'      => 'La preuve ne doit pas dépasser 5 Mo.',
        ]);
        if (!$paiement->estValideDeuxFois()) {
            return back()->with('error', "Ce règlement n'a pas encore reçu ses deux validations : aucune preuve à joindre.");
        }
        if ($paiement->estEffectuee()) {
            return back()->with('error', 'Ce règlement est déjà effectué.');
        }
        if (!$paiement->troisiemeAdministrateur(Auth::user())) {
            return back()->with('error', "Pour des raisons de sécurité, la preuve est téléversée et le règlement finalisé par un troisième administrateur, différent des deux validateurs.");
        }
        $chemin = $request->file('preuve')->store('preuves_paiement', 'public');
        $paiement->update([
            'preuve_paiement' => $chemin,
            'date_preuve'     => now(),
            'user_preuve_id'  => Auth::id(),
            'etat_reglement'  => DemandePaiement::PREUVE_JOINTE,
        ]);

        return back()->with('success', 'Preuve de paiement jointe. Vous pouvez maintenant finaliser le règlement.');
    }

    protected function voirPreuveReglement($paiement)
    {
        if (!$paiement || !$paiement->preuve_paiement || !Storage::disk('public')->exists($paiement->preuve_paiement)) {
            abort(404, 'Aucune preuve jointe.');
        }

        return Storage::disk('public')->response($paiement->preuve_paiement);
    }

    protected function effectuerReglement($paiement)
    {
        if (!$paiement) {
            return back()->with('error', 'Règlement introuvable.');
        }
        if (!$paiement->estValideDeuxFois()) {
            return back()->with('error', "Ce règlement n'a pas encore reçu ses deux validations.");
        }
        if ($paiement->estEffectuee()) {
            return back()->with('info', 'Ce règlement est déjà effectué.');
        }
        if (!$paiement->troisiemeAdministrateur(Auth::user())) {
            return back()->with('error', "Pour des raisons de sécurité, la preuve est téléversée et le règlement finalisé par un troisième administrateur, différent des deux validateurs.");
        }
        if (!$paiement->peutFinaliser()) {
            return back()->with('error', "Joignez d'abord la preuve du paiement : sans elle, l'opération ne peut pas être déclarée effectuée.");
        }
        $paiement->update([
            'etat_reglement'    => DemandePaiement::EFFECTUEE,
            'date_effectuee'    => now(),
            'user_effectuee_id' => Auth::id(),
        ]);

        // LE REÇU PART AU CLIENT OU AU PARTENAIRE (11/09/2026) : le document
        // que le guichet génère, en PDF, une seule fois par règlement, après
        // la réponse. Le règlement est déjà effectué : un envoi qui échoue ne
        // le remet jamais en cause (tout est sous try/catch dans le service).
        \App\Services\RecuDeReglement::envoyerApresFinalisation($paiement, static::class);

        return back()->with('success', 'Règlement effectué : le partenaire le voit désormais dans son espace.');
    }
}
