<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Une entreprise a une raison sociale, pas un prénom.
 *
 * La règle vit à deux endroits — un accesseur PHP pour les objets, une
 * expression SQL pour les listes qui composent le nom dans leur requête. Ces
 * tests garantissent que les deux disent exactement la même chose : c'est la
 * divergence entre elles qui laissait « TEST TEST » sur certains écrans.
 */
class NomClientEntrepriseTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        TypeUser::firstOrCreate(
            ['id' => \Help::$USER_CLIENT],
            ['nom' => 'Client', 'statut' => 1]
        );
    }

    private function client(string $nom, ?string $prenom, string $type): Client
    {
        $compte = User::factory()->create([
            'type_user_id' => \Help::$USER_CLIENT,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        return Client::factory()->create([
            'user_id'     => $compte->id,
            'nom'         => $nom,
            'prenom'      => $prenom,
            'type_client' => $type,
        ]);
    }

    /** Ce que la base répond pour ce client, via l'expression SQL partagée. */
    private function nomSelonSql(Client $client): string
    {
        $ligne = DB::table('client')
            ->selectRaw(Client::sqlNomAffiche() . ' as nom_affiche')
            ->where('client.id', $client->id)
            ->first();

        return (string) $ligne->nom_affiche;
    }

    public static function casDeNommage(): array
    {
        return [
            'entreprise dont le prénom répète le nom' => ['TEST', 'TEST', 'ENTREPRISE', 'TEST'],
            'entreprise sans prénom'                  => ['DIO SARL', '', 'ENTREPRISE', 'DIO SARL'],
            'entreprise avec un prénom différent'     => ['ACME', 'Jean', 'ENTREPRISE', 'ACME'],
            'particulier ordinaire'                   => ['KONAN', 'Jean', 'PARTICULIER', 'KONAN Jean'],
            'particulier sans prénom'                 => ['KONAN', '', 'PARTICULIER', 'KONAN'],
            'particulier dont le prénom répète le nom'=> ['KONAN', 'KONAN', 'PARTICULIER', 'KONAN'],
        ];
    }

    /** @dataProvider casDeNommage */
    public function test_le_nom_affiche_est_le_bon(string $nom, ?string $prenom, string $type, string $attendu): void
    {
        $client = $this->client($nom, $prenom, $type);

        $this->assertSame($attendu, $client->display_name, 'Accesseur PHP');
        $this->assertSame($attendu, $this->nomSelonSql($client), 'Expression SQL');
    }

    public function test_les_deux_regles_ne_peuvent_pas_diverger(): void
    {
        foreach (self::casDeNommage() as $libelle => [$nom, $prenom, $type, ]) {
            $client = $this->client($nom, $prenom, $type);

            $this->assertSame(
                $client->display_name,
                $this->nomSelonSql($client),
                "Divergence PHP / SQL pour : {$libelle}"
            );
        }
    }

    public function test_les_espaces_superflus_sont_ignores(): void
    {
        // Une saisie « TEST » suivie d'espaces ne doit pas échapper à la règle.
        $client = $this->client('TEST', '  TEST  ', 'PARTICULIER');

        $this->assertSame('TEST', $client->display_name);
        $this->assertSame('TEST', $this->nomSelonSql($client));
    }
}
