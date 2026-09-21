<?php

namespace App\Http\Controllers;

use App\Models\DecisionClientTerme;
use App\Models\DemandeCompteClientATerme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La seconde validation des décisions de crédit.
 *
 * Une décision — accorder le statut à terme, le retirer, relever un plafond —
 * est saisie ailleurs (écran des demandes, liste des clients à terme) puis
 * contrôlée ici par un second administrateur.
 *
 * L'e-mail au client part à la SECONDE validation, jamais à la première : il
 * annoncerait sinon un accord qui n'est pas encore acquis.
 */
class DecisionClientTermeController extends Controller
{
    /**
     * RETIRER LE STATUT À TERME.
     *
     * Le geste attendu face à un mauvais payeur : on ferme la ligne de crédit
     * sans effacer ce qui est dû. Les créances en cours restent dues, lisibles
     * et relançables — seul le droit de commander à crédit s'arrête.
     *
     * Comme l'octroi, le retrait attend un second administrateur : couper le
     * crédit d'un client engage la relation commerciale autant que l'ouvrir.
     */
    public function demanderRetrait(Request $request, \App\Models\Client $client)
    {
        $refus = $this->verifierSaisie($client, true);

        if ($refus) {
            return $refus;
        }

        DecisionClientTerme::create([
            'client_id'         => $client->id,
            'type'              => DecisionClientTerme::DESACTIVATION,
            'commentaire'       => $request->input('motif'),
            'ancien_plafond'    => $client->plafond_credit,
            'ancien_delai'      => $client->delai_paiement,
            // Le plafond du jour est conservé : il servira de proposition si le
            // statut est rendu plus tard, plutôt que de repartir de zéro.
            'plafond_credit'    => $client->plafond_credit,
            'delai_paiement'    => $client->delai_paiement,
            'user_valide_id'    => Auth::id(),
            'date_validation_1' => now(),
            'statut'            => DecisionClientTerme::EN_ATTENTE,
        ]);

        return back()->with('decision_succes',
            'Retrait enregistré. Il prendra effet après validation par un second administrateur ; '
            . 'd\'ici là le client garde son crédit.');
    }

    /**
     * RENDRE LE STATUT À TERME.
     *
     * Même contrôle que le retrait. Le plafond proposé est celui d'avant le
     * retrait — repartir de zéro obligerait à retrouver un montant que la
     * décision de retrait avait justement conservé.
     */
    public function demanderReactivation(Request $request, \App\Models\Client $client)
    {
        $refus = $this->verifierSaisie($client, false);

        if ($refus) {
            return $refus;
        }

        $dernierRetrait = DecisionClientTerme::where('client_id', $client->id)
            ->where('type', DecisionClientTerme::DESACTIVATION)
            ->where('statut', DecisionClientTerme::APPLIQUEE)
            ->latest('id')
            ->first();

        $request->validate([
            'plafond_credit' => 'nullable|numeric|min:0',
            'delai_paiement' => 'nullable|integer|min:1|max:365',
        ], [
            'plafond_credit.numeric' => 'Le plafond de crédit doit être un montant.',
            'delai_paiement.max'     => 'Le délai de paiement ne peut excéder 365 jours.',
        ]);

        DecisionClientTerme::create([
            'client_id'         => $client->id,
            'type'              => DecisionClientTerme::ACTIVATION,
            'commentaire'       => $request->input('motif'),
            'ancien_plafond'    => $client->plafond_credit,
            'ancien_delai'      => $client->delai_paiement,
            'plafond_credit'    => $request->filled('plafond_credit')
                ? (float) $request->plafond_credit
                : (float) ($dernierRetrait->plafond_credit ?? $client->plafond_credit ?? 0),
            'delai_paiement'    => $request->filled('delai_paiement')
                ? (int) $request->delai_paiement
                : (int) ($dernierRetrait->delai_paiement ?? $client->delai_paiement ?? 30),
            'user_valide_id'    => Auth::id(),
            'date_validation_1' => now(),
            'statut'            => DecisionClientTerme::EN_ATTENTE,
        ]);

        return back()->with('decision_succes',
            'Réactivation enregistrée. Elle prendra effet après validation par un second administrateur.');
    }

    /**
     * Les deux refus communs aux saisies : table absente, décision déjà en
     * cours, et statut incompatible avec le geste demandé.
     */
    private function verifierSaisie(\App\Models\Client $client, bool $doitEtreATerme)
    {
        if (!DecisionClientTerme::tableExiste()) {
            return back()->with('decision_erreur',
                'La table des décisions de crédit n\'existe pas encore sur ce serveur : lancez la migration.');
        }

        if ($enCours = DecisionClientTerme::enAttentePour((int) $client->id)) {
            return back()->with('decision_erreur',
                'Une décision attend déjà sa validation sur ce client : ' . $enCours->libelleType() . '.');
        }

        $estATerme = (int) $client->client_a_terme === 1;

        if ($doitEtreATerme && !$estATerme) {
            return back()->with('decision_erreur', 'Ce client n\'est pas un client à terme.');
        }

        if (!$doitEtreATerme && $estATerme) {
            return back()->with('decision_erreur', 'Ce client est déjà un client à terme.');
        }

        return null;
    }

    public function valider(DecisionClientTerme $decision)
    {
        $user = Auth::user();

        if (!$decision->attendUneSecondeValidation()) {
            return back()->with('decision_erreur', 'Cette décision a déjà été traitée.');
        }

        if (!in_array((int) ($user->type_user_id ?? 0), [\Help::$USER_SA, \Help::$USER_ADMIN], true)) {
            return back()->with('decision_erreur', 'Seul un administrateur peut valider une décision de crédit.');
        }

        if ((int) $decision->user_valide_id === (int) $user->id) {
            return back()->with('decision_erreur',
                'Vous ne pouvez pas valider la décision que vous avez saisie. Un second administrateur doit la contrôler.');
        }

        // Dernier verrou, par la méthode qui décide aussi d'afficher le bouton :
        // l'écran et le serveur ne peuvent pas diverger.
        if (!$decision->peutEtreValidePar($user)) {
            return back()->with('decision_erreur', 'Vous ne pouvez pas valider cette décision.');
        }

        $decision->update([
            'user_valide2_id'   => $user->id,
            'date_validation_2' => now(),
            'statut'            => DecisionClientTerme::APPLIQUEE,
        ]);

        $decision->appliquer();

        $this->refleterSurLaDemande($decision, true);
        $this->prevenirLeClient($decision, true);

        return back()->with('decision_succes', 'Décision validée et appliquée. ' . $decision->resume());
    }

    public function refuser(DecisionClientTerme $decision)
    {
        $user = Auth::user();

        if (!$decision->attendUneSecondeValidation()) {
            return back()->with('decision_erreur', 'Cette décision a déjà été traitée.');
        }

        if (!$decision->peutEtreRefusePar($user)) {
            return back()->with('decision_erreur',
                'Seul un second administrateur peut refuser cette décision.');
        }

        $decision->update([
            'user_valide2_id'   => $user->id,
            'date_validation_2' => now(),
            'statut'            => DecisionClientTerme::REFUSEE,
        ]);

        // Rien n'est appliqué : le client reste exactement dans l'état où il
        // était avant la saisie.
        return back()->with('decision_succes', 'Décision écartée. Rien n\'a été modifié sur le client.');
    }

    /**
     * Reporte l'issue sur la demande d'origine, s'il y en a une.
     *
     * La demande porte l'historique côté client : la laisser « en attente »
     * après une décision prise donnerait deux vérités contradictoires.
     */
    private function refleterSurLaDemande(DecisionClientTerme $decision, bool $validee): void
    {
        if (!$decision->demande_id) {
            return;
        }

        $demande = DemandeCompteClientATerme::find($decision->demande_id);

        if (!$demande) {
            return;
        }

        if ($decision->type === DecisionClientTerme::ACTIVATION) {
            $demande->update([
                'approuve'          => 1,
                'user_id'           => $decision->user_valide_id,
                'plafond_credit'    => $decision->plafond_credit,
                'delai_paiement'    => $decision->delai_paiement,
                'commentaire_admin' => $decision->commentaire,
                'decided_at'        => now(),
            ]);

            return;
        }

        if ($decision->type === DecisionClientTerme::REFUS_DEMANDE) {
            $demande->update([
                'approuve'    => 2,
                'user_id'     => $decision->user_valide_id,
                'motif_refus' => $decision->commentaire,
                'decided_at'  => now(),
            ]);
        }
    }

    /**
     * L'e-mail part ICI, à la seconde validation.
     *
     * Il est enveloppé : un serveur de messagerie indisponible ne doit pas
     * défaire une décision déjà prise et déjà appliquée.
     */
    private function prevenirLeClient(DecisionClientTerme $decision, bool $validee): void
    {
        if (!$decision->demande_id) {
            return;
        }

        $demande = DemandeCompteClientATerme::with('client.user')->find($decision->demande_id);

        if (!$demande) {
            return;
        }

        try {
            $destinataire = $demande->client->user->email ?? $demande->client->email ?? null;

            if (empty($destinataire)) {
                return;
            }

            if ($decision->type === DecisionClientTerme::ACTIVATION) {
                \Mail::to($destinataire)->send(new \App\Mail\DemandeClientATermeApprouvee($demande->fresh(['client.user'])));
            } elseif ($decision->type === DecisionClientTerme::REFUS_DEMANDE) {
                \Mail::to($destinataire)->send(new \App\Mail\DemandeClientATermeRefusee($demande->fresh(['client.user'])));
            }
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email décision client à terme : ' . $e->getMessage());
        }
    }
}
