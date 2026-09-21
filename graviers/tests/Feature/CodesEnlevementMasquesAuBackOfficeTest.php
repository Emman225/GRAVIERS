<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * LE CODE D'ENLÈVEMENT NE REGARDE QUE LE CLIENT ET LE FOURNISSEUR.
 *
 * Points 14 et 18 du 07/09/2026 : les écrans du back-office (administrateur,
 * gestionnaire) ne montrent plus les codes d'enlèvement. Le client, lui,
 * continue de voir les siens (point 17).
 */
class CodesEnlevementMasquesAuBackOfficeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_les_ecrans_du_back_office_ne_montrent_aucun_code_d_enlevement(): void
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur en base.');
        }

        // Des codes assez longs pour ne pas apparaître par hasard dans une page.
        $codes = Enlevement::whereNotNull('code_enleve')
            ->get()
            ->pluck('code_enleve')
            ->filter(fn ($c) => strlen((string) $c) >= 6)
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            $this->markTestSkipped("Aucun code d'enlèvement en base.");
        }

        Auth::guard('web')->login($admin);

        foreach (['show.bonAttente', 'show.bonValides', 'show.dettesFournisseurs', 'show.recapLivraison'] as $route) {
            $reponse = $this->get(route($route));
            $reponse->assertOk();
            foreach ($codes as $code) {
                $this->assertStringNotContainsString($code, $reponse->getContent(),
                    "La page {$route} affiche encore le code d'enlèvement {$code}.");
            }
        }
    }

    public function test_le_client_voit_ses_codes_avec_de_quoi_les_copier_et_les_partager(): void
    {
        $enlevement = Enlevement::whereNotNull('code_enleve')
            ->whereHas('livraison', fn ($q) => $q->where('accepte', 1)->whereNotNull('detail_commande_id'))
            ->with('livraison.detailCommande.commande.client.user')
            ->get()
            ->first(fn ($e) => $e->livraison?->detailCommande?->commande?->client?->user);

        if (!$enlevement) {
            $this->markTestSkipped('Aucun enlèvement rattaché à une commande de client.');
        }

        $commande = $enlevement->livraison->detailCommande->commande;
        Auth::guard('web')->login($commande->client->user);

        $reponse = $this->get(route('client.monCompte'));
        $reponse->assertOk();
        // Depuis le 08/09/2026 : le bon d'enlèvement n'est montré que si le
        // client retire lui-même ; sinon, seul le code de livraison l'est.
        if ((int) $commande->est_livrable === 1) {
            $reponse->assertSee($enlevement->livraison->numero);
            $reponse->assertDontSee($enlevement->code_enleve);
        } else {
            $reponse->assertSee($enlevement->code_enleve);
        }
        $reponse->assertSee('code-livraison__copier', false);
        $reponse->assertSee('https://wa.me/?text=', false);
    }
}
