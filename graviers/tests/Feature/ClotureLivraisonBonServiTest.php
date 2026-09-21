<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Livraison;
use App\Models\Livreur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * On ne clôture pas une livraison dont le fournisseur n'a pas servi le bon.
 *
 * Le livreur pouvait clôturer une course — et être crédité — alors que le bon
 * d'enlèvement n'avait jamais été servi. La commande passait TERMINEE et
 * rejoignait « commandes traitées » pendant que le bon restait « en attente
 * d'enlèvement » sur l'écran du fournisseur.
 *
 * Le coût n'était pas seulement cosmétique : les trois écrans de chiffre
 * d'affaires ne comptent que les bons portant une `fournisseur_validation`. La
 * vente n'apparaissait donc NULLE PART, et le fournisseur n'était dû de rien
 * alors que sa marchandise était partie.
 *
 * Deux points d'entrée, deux gardes : le livreur clôture depuis son application
 * (API) ou depuis le site. Ce test couvre le site.
 */
class ClotureLivraisonBonServiTest extends TestCase
{
    use DatabaseTransactions;

    /** Une livraison affectée à un livreur, prête à être clôturée. */
    private function uneLivraison(): ?array
    {
        $modele = Livraison::whereNotNull('livreur_id')
            ->whereNotNull('detail_commande_id')
            ->where('provenance', \Help::$COMMANDE)
            ->first();

        if (!$modele) {
            return null;
        }

        $livreur = Livreur::find($modele->livreur_id);

        if (!$livreur || !$livreur->user_id) {
            return null;
        }

        $livraison = $modele->replicate();
        $livraison->numero         = 'CLO-' . uniqid();
        $livraison->etat_livraison = \Help::$LIVRAISON_EN_ATTENTE;
        $livraison->statut         = \Help::$STATUT_ACTIF;
        $livraison->save();

        return [$livraison, $livreur];
    }

    /** Un bon rattaché à cette livraison, servi ou non. */
    private function unBon(Livraison $livraison, bool $servi): Enlevement
    {
        $modele = Enlevement::whereNotNull('fournisseur_id')->first();

        if (!$modele) {
            $this->markTestSkipped('Aucun bon exploitable comme modele.');
        }

        return Enlevement::create([
            'fournisseur_id'         => $modele->fournisseur_id,
            'livraison_id'           => $livraison->id,
            'produit_id'             => $modele->produit_id,
            'qte'                    => 5,
            'code_enleve'            => 'BON-' . uniqid(),
            'statut'                 => \Help::$STATUT_ACTIF,
            'fournisseur_validation' => $servi ? now() : null,
            'qte_servi'              => $servi ? 5 : null,
        ]);
    }

    private function cloturer(Livreur $livreur, Livraison $livraison): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');

        return $this->actingAs($livreur->user)->post('/validation-livraison', [
            'code' => $livraison->numero,
        ]);
    }

    public function test_un_bon_non_servi_empeche_la_cloture(): void
    {
        $contexte = $this->uneLivraison();

        if (!$contexte) {
            $this->markTestSkipped('Aucune livraison exploitable.');
        }

        [$livraison, $livreur] = $contexte;

        $this->unBon($livraison, false);

        $this->cloturer($livreur, $livraison);

        $this->assertNotSame(\Help::$LIVRAISON_LIVREE, $livraison->fresh()->etat_livraison,
            'La livraison ne doit pas passer a LIVREE tant que le bon n est pas servi.');
    }

    public function test_le_message_dit_quoi_faire(): void
    {
        $contexte = $this->uneLivraison();

        if (!$contexte) {
            $this->markTestSkipped('Aucune livraison exploitable.');
        }

        [$livraison, $livreur] = $contexte;

        $bon = $this->unBon($livraison, false);

        $this->cloturer($livreur, $livraison);

        // Cle « fail » : la vue du livreur l affiche, et Flasher ne la capte pas.
        $message = (string) session('fail');

        $this->assertStringContainsString('fournisseur', $message);
        $this->assertStringContainsString($bon->code_enleve, $message,
            'Le message doit nommer le bon a faire valider.');
    }

    public function test_un_bon_servi_laisse_cloturer(): void
    {
        // Le garde-fou ne doit bloquer que le cas qu il vise.
        $contexte = $this->uneLivraison();

        if (!$contexte) {
            $this->markTestSkipped('Aucune livraison exploitable.');
        }

        [$livraison, $livreur] = $contexte;

        $this->unBon($livraison, true);

        $this->cloturer($livreur, $livraison);

        $this->assertSame(\Help::$LIVRAISON_LIVREE, $livraison->fresh()->etat_livraison);
    }

    public function test_une_livraison_sans_bon_n_est_pas_bloquee(): void
    {
        // Une location ou une demande de livraison n a pas de bon fournisseur :
        // les bloquer arreterait des flux qui ne concernent aucun fournisseur.
        $contexte = $this->uneLivraison();

        if (!$contexte) {
            $this->markTestSkipped('Aucune livraison exploitable.');
        }

        [$livraison, $livreur] = $contexte;

        $this->assertSame(0, Enlevement::where('livraison_id', $livraison->id)->count());

        $this->cloturer($livreur, $livraison);

        $this->assertSame(\Help::$LIVRAISON_LIVREE, $livraison->fresh()->etat_livraison);
    }

    public function test_un_bon_desactive_ne_bloque_pas(): void
    {
        // Un bon retire du circuit n engage plus le fournisseur.
        $contexte = $this->uneLivraison();

        if (!$contexte) {
            $this->markTestSkipped('Aucune livraison exploitable.');
        }

        [$livraison, $livreur] = $contexte;

        $bon = $this->unBon($livraison, false);
        $bon->update(['statut' => \Help::$STATUT_INACTIF]);

        $this->cloturer($livreur, $livraison);

        $this->assertSame(\Help::$LIVRAISON_LIVREE, $livraison->fresh()->etat_livraison);
    }
}
