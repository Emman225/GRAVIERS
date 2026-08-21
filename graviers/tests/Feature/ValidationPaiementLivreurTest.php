<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\DemandePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\PaiementLivreur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La validation d'un paiement livreur saisi par un administrateur.
 *
 * Elle avait été coupée en même temps que l'enregistrement, pour la même
 * raison : la demande de paiement validée n'écrivait rien dans
 * `paiement_livreur`. La cause traitée, l'enregistrement a été rouvert — mais
 * pas la validation. Les paiements saisis restaient donc bloqués à l'état
 * « en attente d'un autre admin », que personne ne pouvait plus valider.
 */
class ValidationPaiementLivreurTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0: Livraison, 1: User, 2: User} */
    private function acteurs(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->take(2)->get();

        $course = Livraison::whereNotNull('livreur_id')->first();

        if ($admins->count() < 2 || !$course) {
            $this->markTestSkipped('Il faut deux administrateurs et une course.');
        }

        foreach ($admins as $a) {
            if (!$a->agence_id && ($agence = Agence::first())) {
                $a->update(['agence_id' => $agence->id]);
            }
        }

        $course->update([
            'etat_livraison' => \Help::$LIVRAISON_LIVREE,
            'cout_livraison' => 2000,
            'forfait_base'   => 0,
            'frais_km'       => 0,
        ]);

        PaiementLivreur::where('livraison_id', $course->id)->forceDelete();
        DemandePaiement::where('user_id', Livreur::find($course->livreur_id)?->user_id)->delete();

        return [$course->fresh(), $admins[0]->fresh(), $admins[1]->fresh()];
    }

    private function saisir(Livraison $course, User $par, float $montant = 2000): PaiementLivreur
    {
        URL::forceRootUrl('');

        $this->actingAs($par)->post('/livreurs/paiements', [
            'livraison_ids'    => [$course->id],
            'montant'          => $montant,
            'mode_paiement_id' => 6,
            'date_paiement'    => now()->toDateString(),
        ]);

        $paiement = PaiementLivreur::where('livraison_id', $course->id)->first();

        $this->assertNotNull($paiement, "Le paiement n'a pas été enregistré.");

        return $paiement;
    }

    public function test_un_second_administrateur_peut_valider(): void
    {
        [$course, $premier, $second] = $this->acteurs();

        $paiement = $this->saisir($course, $premier);
        $this->assertSame(2, (int) $paiement->statut);

        $this->actingAs($second)
            ->post('/livreurs/paiements/' . $paiement->id . '/valider');

        // On controle l'ETAT et non le message : le flash n'est pas lisible
        // apres redirection dans un test, et c'est l'etat qui fait foi.
        $this->assertSame(1, (int) $paiement->fresh()->statut);
        $this->assertSame((int) $second->id, (int) $paiement->fresh()->user_valide2_id);
    }

    public function test_la_course_soldee_est_marquee_payee(): void
    {
        [$course, $premier, $second] = $this->acteurs();

        $paiement = $this->saisir($course, $premier);

        $this->actingAs($second)->post('/livreurs/paiements/' . $paiement->id . '/valider');

        $this->assertSame('Payée', $course->fresh()->statut_paiement_livreur);
        $this->assertSame(0.0, $course->fresh()->resteAPayerLivreur());
    }

    public function test_celui_qui_a_saisi_ne_peut_pas_valider(): void
    {
        [$course, $premier] = $this->acteurs();

        $paiement = $this->saisir($course, $premier);

        $this->actingAs($premier)
            ->post('/livreurs/paiements/' . $paiement->id . '/valider');

        // Toujours en attente : la seconde signature reste due, et elle ne
        // peut pas venir de celui qui a saisi.
        $this->assertSame(2, (int) $paiement->fresh()->statut);
        $this->assertNull($paiement->fresh()->user_valide2_id);
    }

    public function test_un_paiement_deja_valide_ne_se_revalide_pas(): void
    {
        [$course, $premier, $second] = $this->acteurs();

        $paiement = $this->saisir($course, $premier);

        $this->actingAs($second)->post('/livreurs/paiements/' . $paiement->id . '/valider');

        $valideur = $paiement->fresh()->user_valide2_id;
        $date     = $paiement->fresh()->date_validation_2;

        $this->actingAs($second)->post('/livreurs/paiements/' . $paiement->id . '/valider');

        // Rien n'a bouge : une seconde validation ne se rejoue pas.
        $this->assertSame((int) $valideur, (int) $paiement->fresh()->user_valide2_id);
        $this->assertEquals($date, $paiement->fresh()->date_validation_2);
    }
}
