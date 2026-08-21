<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Ce que l'état de parrainage compte réellement.
 *
 * LE FILTRE DE DATES ÉTAIT COMMENTÉ, dans la requête comme dans le formulaire :
 * le contrôleur calculait une période que personne n'appliquait, et l'écran
 * affichait tout l'historique quoi qu'on demande. La borne haute portait de
 * surcroît « 29:59:59 », une heure qui n'existe pas.
 *
 * LES PAIEMENTS NON RÉGLÉS ÉTAIENT ADMIS : la condition retenait un paiement
 * validé OU dont `montant_total = montant_restant`. Ce reste tombant à zéro une
 * fois le paiement soldé, le second terme désignait exactement les paiements
 * sur lesquels rien n'avait été versé.
 *
 * Le regroupement se faisait enfin sur les libellés, si bien que deux clients
 * homonymes joignables au même numéro n'occupaient qu'une ligne.
 */
class EtatParrainagePerimetreTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    private function unApporteur(): Apporteur
    {
        $apporteur = Apporteur::whereNotNull('user_id')->first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur.');
        }

        return $apporteur;
    }

    /** Un filleul tout neuf, rattaché à un apporteur existant. */
    private function unFilleul(string $nom = 'Filleul', string $contact = '0700000000'): Client
    {
        return Client::create([
            'user_id'     => $this->unAdmin()->id,
            'nom'         => $nom,
            'prenom'      => 'Recette',
            'email'       => 'filleul-' . uniqid() . '@example.test',
            'contact1'    => $contact,
            'type_client' => 'PARTICULIER',
            'parrain_id'  => $this->unApporteur()->id,
            'statut'      => \Help::$STATUT_ACTIF,
        ]);
    }

    private function unPaiement(Client $client, float $montant, string $date, int $statut = 1): Paiement
    {
        $p = Paiement::create([
            'client_id'       => $client->id,
            'code'            => 'PP' . substr((string) uniqid(), -8),
            'libelle'         => 'Paiement de recette',
            'montant_total'   => $montant,
            'montant_restant' => $statut === 1 ? 0 : $montant,
            'statut'          => $statut,
        ]);

        $p->forceFill(['created_at' => $date])->save();

        return $p->fresh();
    }

    private function ecran(array $filtres = []): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())
            ->get('/etat-de-parrainage' . ($filtres ? '?' . http_build_query($filtres) : ''));
        $reponse->assertOk();

        return $reponse;
    }

    /** Ce que l'écran retient pour un client donné, tous apporteurs confondus. */
    private function totalDe(Client $client, array $filtres = []): float
    {
        $total = 0.0;

        foreach ($this->ecran($filtres)->viewData('apporteurs') as $a) {
            foreach ($a->clients as $c) {
                if ((int) $c->clientId === $client->id) {
                    $total += (float) $c->total;
                }
            }
        }

        return $total;
    }

    // ------------------------------------------------------------ LES DATES

    public function test_le_filtre_de_dates_est_reellement_applique(): void
    {
        $client = $this->unFilleul();

        $this->unPaiement($client, 5000, '2021-03-10 09:00:00');
        $this->unPaiement($client, 3000, '2021-08-20 09:00:00');

        // La ligne qui appliquait la période était en commentaire : les deux
        // paiements remontaient quelle que soit la demande.
        $this->assertSame(5000.0, $this->totalDe($client, ['du' => '2021-03-01', 'au' => '2021-03-31']));
        $this->assertSame(3000.0, $this->totalDe($client, ['du' => '2021-08-01', 'au' => '2021-08-31']));
        $this->assertSame(0.0, $this->totalDe($client, ['du' => '2021-05-01', 'au' => '2021-05-31']));
    }

    public function test_la_borne_haute_couvre_la_journee_entiere(): void
    {
        $client = $this->unFilleul();

        // Le dernier jour de la période, tard : la borne portait « 29:59:59 »,
        // une heure inexistante que le serveur aurait refusée.
        $this->unPaiement($client, 4200, '2021-04-30 23:45:00');

        $this->assertSame(4200.0, $this->totalDe($client, ['du' => '2021-04-01', 'au' => '2021-04-30']));
    }

    public function test_sans_periode_tout_l_historique_remonte(): void
    {
        $client = $this->unFilleul();

        $this->unPaiement($client, 5000, '2021-03-10 09:00:00');
        $this->unPaiement($client, 3000, '2024-08-20 09:00:00');

        $this->assertSame(8000.0, $this->totalDe($client));

        $reponse = $this->ecran();
        $this->assertNull($reponse->viewData('du'));
        $this->assertNull($reponse->viewData('au'));
    }

    // -------------------------------------------------------- LE PÉRIMÈTRE

    public function test_un_paiement_non_valide_ne_compte_pas(): void
    {
        $client = $this->unFilleul();

        $this->unPaiement($client, 5000, '2021-03-10 09:00:00');

        // Non validé, et rien n'a été versé dessus : c'est précisément ce que
        // l'ancienne condition faisait entrer dans le total.
        $this->unPaiement($client, 9000, '2021-03-11 09:00:00', statut: 2);

        $this->assertSame(5000.0, $this->totalDe($client));
    }

    public function test_un_client_sans_parrain_n_apparait_pas(): void
    {
        $orphelin = Client::create([
            'user_id'     => $this->unAdmin()->id,
            'nom'         => 'Sans parrain',
            'prenom'      => 'Recette',
            'email'       => 'orphelin-' . uniqid() . '@example.test',
            'contact1'    => '0711111111',
            'type_client' => 'PARTICULIER',
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        $this->unPaiement($orphelin, 6000, '2021-03-10 09:00:00');

        $this->assertSame(0.0, $this->totalDe($orphelin));
    }

    // ------------------------------------------------------ LE REGROUPEMENT

    public function test_deux_filleuls_homonymes_ne_fusionnent_pas(): void
    {
        $unNom  = 'Homonyme' . substr((string) uniqid(), -6);
        $unTel  = '0755555555';

        // Mêmes nom, prénom, type et contact : tout ce sur quoi on groupait.
        $premier = $this->unFilleul(nom: $unNom, contact: $unTel);
        $second  = $this->unFilleul(nom: $unNom, contact: $unTel);

        $this->unPaiement($premier, 1000, '2021-03-10 09:00:00');
        $this->unPaiement($second, 2000, '2021-03-10 09:00:00');

        $this->assertSame(1000.0, $this->totalDe($premier));
        $this->assertSame(2000.0, $this->totalDe($second));
    }

    // ----------------------------------------------------------- LES TOTAUX

    public function test_les_sous_totaux_par_apporteur_font_le_total_general(): void
    {
        $client = $this->unFilleul();
        $this->unPaiement($client, 7000, '2021-03-10 09:00:00');

        $reponse = $this->ecran();

        $apporteurs = $reponse->viewData('apporteurs');

        $this->assertSame(
            round((float) collect($apporteurs)->sum('total'), 2),
            round((float) $reponse->viewData('totalGeneral'), 2)
        );

        foreach ($apporteurs as $a) {
            $this->assertSame(
                round((float) collect($a->clients)->sum(fn ($c) => (float) $c->total), 2),
                round((float) $a->total, 2),
                "Le sous-total de « {$a->nom} » ne fait pas la somme de ses filleuls."
            );
        }
    }

    public function test_la_page_repond_sans_aucun_paiement(): void
    {
        $reponse = $this->ecran(['du' => '1990-01-01', 'au' => '1990-12-31']);

        $this->assertCount(0, $reponse->viewData('apporteurs'));
        $reponse->assertSee('Aucun paiement de filleul sur cette période.');
    }
}
