<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LE RÉGIME D'IMPOSITION DU CLIENT DOIT ÊTRE SAISISSABLE.
 *
 * La colonne `client.regime_imposition` existait, et `FneService` la lisait
 * depuis toujours pour l'imprimer sur la facture — la DGI l'attend sur une
 * facture entre entreprises. Mais rien ne permettait de la renseigner : ni
 * champ au formulaire d'inscription, ni enregistrement au contrôleur, ni même
 * la ligne `fillable` sans laquelle la valeur aurait été ignorée en silence.
 *
 * La ligne « Régime d'imposition : » sortait donc vide sur chaque facture,
 * comme on le voit sur le reçu généré le 28/08/2026.
 */
class RegimeImpositionClientTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * LE FORMULAIRE PORTE DEUX FOIS LE BLOC ENTREPRISE.
     *
     * Celui rendu par le serveur au retour d'une erreur de saisie, et celui que
     * le script injecte quand on choisit « Entreprise ». N'en traiter qu'un
     * ferait apparaître le champ dans un seul des deux cas — et disparaître la
     * valeur saisie au moindre refus du formulaire.
     */
    public function test_le_champ_figure_dans_les_deux_copies_du_formulaire(): void
    {
        $vue = file_get_contents(resource_path('views/client/register.blade.php'));

        $this->assertSame(
            2,
            substr_count($vue, 'name="regime_imposition"'),
            "Le champ doit figurer dans les DEUX blocs entreprise du formulaire : "
            . "celui du serveur et celui injecté par le script."
        );
    }

    /** UN CHAMP AFFICHÉ MAIS NON ENREGISTRÉ SERAIT PIRE QUE PAS DE CHAMP. */
    public function test_le_controleur_enregistre_le_regime(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ClientController.php'));
        $code = preg_replace('!/\*.*?\*/!s', '', $source);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        // Inscription : le régime est repris tel que saisi (un code, validé
        // par RegimeImposition::regle()).
        $this->assertSame(
            1,
            substr_count($code, "'regime_imposition' => \$request->regime_imposition"),
            "L'inscription doit enregistrer le régime."
        );

        // Modification de la fiche : le formulaire ne porte PAS ce champ.
        // Reprendre \$request->regime_imposition sans condition effaçait le
        // régime à chaque enregistrement (07/09/2026) ; il n'est repris que
        // s'il est réellement envoyé.
        $this->assertStringContainsString("\$request->filled('regime_imposition')", $code,
            "La modification de la fiche ne doit toucher au régime que s'il est envoyé.");

        $this->assertStringContainsString('"regime_imposition" => "required', $code,
            "Le régime doit être exigé d'une entreprise : c'est une mention "
            . 'obligatoire sur sa facture.');
    }

    /**
     * SANS `fillable`, LA VALEUR EST IGNORÉE EN SILENCE.
     *
     * C'est le piège de ce correctif : le champ s'affiche, le contrôleur le
     * transmet, et Eloquent le jette sans un mot.
     */
    public function test_la_valeur_est_reellement_enregistree(): void
    {
        $user = DB::table('users')->value('id');

        if (!$user) {
            $this->markTestSkipped('Base de travail sans utilisateur.');
        }

        $client = Client::create([
            'user_id'           => $user,
            'nom'               => 'Essai',
            'prenom'            => 'Regime',
            'email'             => 'reg-' . uniqid() . '@example.test',
            'contact1'          => '0700000000',
            'type_client'       => 'ENTREPRISE',
            'regime_imposition' => 'RSI — Régime Simplifié',
        ]);

        $this->assertSame(
            'RSI — Régime Simplifié',
            $client->fresh()->regime_imposition,
            "La valeur a été ignorée : « regime_imposition » manque au fillable "
            . 'du modèle Client.'
        );
    }

    /** LA FACTURE DOIT RECEVOIR CE QUE LE CLIENT A DÉCLARÉ. */
    public function test_la_facture_recoit_le_regime_du_client(): void
    {
        $user = DB::table('users')->value('id');

        if (!$user) {
            $this->markTestSkipped('Base de travail sans utilisateur.');
        }

        $client = Client::create([
            'user_id'           => $user,
            'nom'               => 'Essai',
            'prenom'            => 'Regime',
            'email'             => 'reg-' . uniqid() . '@example.test',
            'contact1'          => '0700000000',
            'type_client'       => 'ENTREPRISE',
            'regime_imposition' => 'RNI — Régime Normal',
        ]);

        $donnees = FneService::getDonneesFne(null, $client->fresh());

        // Depuis le 07/09/2026, la facture imprime l'INTITULÉ du régime, et
        // comprend aussi bien le code que l'ancien libellé stocké.
        $this->assertSame(
            "Réel normal d'imposition",
            $donnees['fne_client']['regime_imposition'],
            "La ligne « Régime d'imposition » sortira vide ou avec l'ancien texte sur la facture."
        );
    }
}
