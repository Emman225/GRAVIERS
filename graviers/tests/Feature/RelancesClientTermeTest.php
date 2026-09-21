<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La liste « À relancer aujourd'hui » retrouve enfin les factures en retard.
 *
 * La sélection exigeait une `date_echeance` non nulle. Or cette colonne n'est
 * écrite NULLE PART : aucun écran, aucun service ne la renseigne. Aucune
 * facture ne passait donc le filtre, et la liste était vide en permanence — non
 * parce que les clients payaient, mais parce que la requête ne pouvait rien
 * trouver. Aucune relance n'a jamais été proposée depuis la mise en service.
 *
 * L'échéance est désormais calculée : date de facture + délai de paiement
 * accordé au client. C'est déjà la règle de la balance âgée et de l'état des
 * créances ; les trois écrans disent enfin la même chose.
 */
class RelancesClientTermeTest extends TestCase
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

    /** Un client à terme, avec le délai de paiement voulu. */
    private function unClientATerme(int $delai): Client
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        $client->update([
            'client_a_terme' => 1,
            'delai_paiement' => $delai,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        return $client->fresh();
    }

    /** Une facture impayée, émise il y a $joursAge jours. */
    private function uneFacture(Client $client, int $joursAge, float $montant = 500000): Facture
    {
        $facture = Facture::create([
            'numero'     => 'FAC-' . uniqid(),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $client->id,
            'montant'    => $montant,
            'statut'     => \Help::$STATUT_ACTIF,
        ]);

        // created_at porte la date de facture : c'est d'elle que part le délai.
        $facture->created_at = now()->subDays($joursAge);
        $facture->save();

        return $facture->fresh();
    }

    private function aRelancer(): \Illuminate\Support\Collection
    {
        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/clients-terme/relances');

        $reponse->assertOk();

        return collect($reponse->viewData('aRelancer'));
    }

    private function delaiRelance(): int
    {
        return (int) (Configuration::first()?->delai_relance_standard ?? 7);
    }

    public function test_une_facture_echue_apparait_enfin(): void
    {
        $client = $this->unClientATerme(30);

        // 30 jours de delai + la tolerance de relance, largement depasses.
        $facture = $this->uneFacture($client, 30 + $this->delaiRelance() + 15);

        $numeros = $this->aRelancer()->pluck('numero')->all();

        $this->assertContains($facture->numero, $numeros,
            'Une facture largement echue doit figurer dans la liste a relancer.');
    }

    public function test_une_facture_encore_dans_les_delais_n_apparait_pas(): void
    {
        $client = $this->unClientATerme(30);

        // Emise hier : l echeance est dans 29 jours.
        $facture = $this->uneFacture($client, 1);

        $this->assertNotContains($facture->numero, $this->aRelancer()->pluck('numero')->all());
    }

    public function test_l_echeance_affichee_est_celle_qui_est_calculee(): void
    {
        // La colonne etant vide, l afficher laisserait le champ a blanc.
        $client  = $this->unClientATerme(30);
        $facture = $this->uneFacture($client, 60);

        $ligne = $this->aRelancer()->firstWhere('numero', $facture->numero);

        $this->assertNotNull($ligne);
        $this->assertNotEmpty($ligne->date_echeance, 'L echeance doit etre renseignee.');

        $this->assertSame(
            $facture->fresh()->echeance()->format('Y-m-d'),
            $ligne->date_echeance
        );
    }

    public function test_le_retard_est_compte_depuis_l_echeance(): void
    {
        $client  = $this->unClientATerme(30);
        $facture = $this->uneFacture($client, 100);

        $ligne = $this->aRelancer()->firstWhere('numero', $facture->numero);

        $this->assertNotNull($ligne);

        // 100 jours ecoules, 30 de delai : 70 jours de retard.
        $this->assertSame(70, (int) $ligne->jours_retard);
    }

    public function test_une_facture_soldee_ne_se_relance_pas(): void
    {
        $client  = $this->unClientATerme(30);
        $facture = $this->uneFacture($client, 90, 0);

        $this->assertNotContains($facture->numero, $this->aRelancer()->pluck('numero')->all(),
            'Sans reste a payer, il n y a rien a relancer.');
    }

    public function test_un_client_sans_delai_n_est_pas_relance(): void
    {
        // Sans delai de paiement, aucune echeance ne peut etre etablie : on ne
        // relance pas sur une date inventee.
        $client  = $this->unClientATerme(0);
        $facture = $this->uneFacture($client, 200);

        $this->assertNotContains($facture->numero, $this->aRelancer()->pluck('numero')->all());
    }

    public function test_un_client_au_comptant_n_est_pas_concerne(): void
    {
        $client = $this->unClientATerme(30);
        $facture = $this->uneFacture($client, 90);

        $client->update(['client_a_terme' => 0]);

        $this->assertNotContains($facture->numero, $this->aRelancer()->pluck('numero')->all());
    }
}
