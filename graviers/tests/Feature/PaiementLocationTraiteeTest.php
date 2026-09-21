<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA COLONNE « PAIEMENT » DOIT DIRE CE QUI A ÉTÉ ENCAISSÉ.
 *
 * Signalé le 03/09/2026 : trois locations intégralement encaissées au guichet
 * — reçus RC-2026-006, 008 et 009, validés par un second administrateur —
 * affichaient « Aucun » sur /locations-traitees.
 *
 * Cet essai rejoue le parcours entier : encaissement au guichet, puis
 * validation par un AUTRE administrateur, et vérifie que la liste le dit.
 */
class PaiementLocationTraiteeTest extends TestCase
{
    use DatabaseTransactions;

    private function deuxAdministrateurs(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', 1)
            ->take(2)
            ->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Deux administrateurs actifs sont nécessaires : '
                . 'un encaissement ne compte qu\'après validation par un second.');
        }

        // LE GUICHET EXIGE UNE AGENCE, et aucun compte n'en a après une remise
        // à zéro de la base. On la pose ici, dans la transaction de l'essai :
        // un essai qui se saute ne prouve rien.
        $agence = \App\Models\Agence::first();

        if (!$agence) {
            $this->markTestSkipped('Aucune agence en base.');
        }

        foreach ($admins as $u) {
            $u->update(['agence_id' => $agence->id]);
        }

        return [$admins[0], $admins[1]];
    }

    private function uneLocationAEncaisser(): Location
    {
        $client = Client::whereNotNull('user_id')->firstOrFail();

        return Location::create([
            // `location.numero` est un varchar(20) : le numéro doit y tenir.
            'numero' => \Help::genererNumeroUnique('location'),
            'client_id' => $client->id,
            'mode_paiement_id' => ModePaiement::first()?->id,
            'montant_total' => 100000,
            'etat_location' => \Help::$LOCATION_EN_COURS,
            'est_livrable' => 1,
            'statut' => 1,
            'caution' => 0,
        ]);
    }

    public function test_une_location_encaissee_puis_validee_n_affiche_plus_aucun(): void
    {
        [$caissier, $valideur] = $this->deuxAdministrateurs();
        $location = $this->uneLocationAEncaisser();

        // 1. Le guichet enregistre l'encaissement du montant entier.
        $this->actingAs($caissier)->post(route('show.encaissements.locations.store'), [
            'numero_location' => $location->numero,
            'notes' => 'Recette : encaissement de la location',
            'mode_paiement_id' => ModePaiement::first()->id,
            'montant' => $location->montantAPayer(),
        ]);

        $paiement = \App\Models\Paiement::where('service', \Help::$LOCATION)
            ->where('service_id', $location->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($paiement, "L'encaissement n'a pas été enregistré.");

        // Tant qu'il n'est pas validé, il ne compte pas : c'est voulu.
        $this->assertSame(1, (int) $location->fresh()->statut,
            "Un encaissement non validé ne doit pas encore solder la location.");

        // 2. Un AUTRE administrateur le valide.
        $this->actingAs($valideur)
            ->post(route('show.encaissements.locations.valider', $paiement->id));

        // 3. La location doit se dire soldée.
        $this->assertSame(3, (int) $location->fresh()->statut,
            'La location reste marquée « Aucun » alors que son encaissement a '
            . 'été validé : le gestionnaire croit le client débiteur.');
    }

    /** ET LA LISTE DOIT L'AFFICHER. */
    public function test_la_liste_des_locations_traitees_affiche_le_paiement(): void
    {
        [$caissier, $valideur] = $this->deuxAdministrateurs();
        $location = $this->uneLocationAEncaisser();

        $this->actingAs($caissier)->post(route('show.encaissements.locations.store'), [
            'numero_location' => $location->numero,
            'notes' => 'Recette : encaissement de la location',
            'mode_paiement_id' => ModePaiement::first()->id,
            'montant' => $location->montantAPayer(),
        ]);

        $paiement = \App\Models\Paiement::where('service', \Help::$LOCATION)
            ->where('service_id', $location->id)->latest('id')->first();

        $this->actingAs($valideur)
            ->post(route('show.encaissements.locations.valider', $paiement->id));

        $html = $this->actingAs($valideur)->get('/locations-traitees')->getContent();

        // La ligne de CETTE location, et ce que dit sa colonne « Paiement ».
        $this->assertStringContainsString($location->numero, $html,
            'La location a disparu de la liste des locations traitées.');

        preg_match(
            '#' . preg_quote($location->numero, '#') . '(.*?)</tr>#s',
            $html,
            $ligne
        );

        $this->assertNotEmpty($ligne, 'Ligne introuvable dans le tableau.');
        $this->assertStringContainsString('Soldé', $ligne[1],
            'La colonne « Paiement » affiche encore « Aucun » pour une '
            . 'location entièrement encaissée et validée.');
    }

    /**
     * LE DRAPEAU PEUT MENTIR ; L'ARGENT, NON.
     *
     * C'est le cas exact des trois locations signalees : encaissees et
     * validees, mais `location.statut` reste a 1 parce qu'elles l'ont ete
     * avant que le guichet ne pose ce drapeau. La colonne doit lire l'argent.
     */
    public function test_une_location_payee_dont_le_drapeau_est_reste_en_arriere(): void
    {
        [$caissier, $valideur] = $this->deuxAdministrateurs();
        $location = $this->uneLocationAEncaisser();

        $this->actingAs($caissier)->post(route('show.encaissements.locations.store'), [
            'numero_location' => $location->numero,
            'notes' => 'Recette : encaissement de la location',
            'mode_paiement_id' => ModePaiement::first()->id,
            'montant' => $location->montantAPayer(),
        ]);

        $paiement = \App\Models\Paiement::where('service', \Help::$LOCATION)
            ->where('service_id', $location->id)->latest('id')->first();

        $this->actingAs($valideur)
            ->post(route('show.encaissements.locations.valider', $paiement->id));

        // On remet le drapeau en arriere : l'etat des locations d'avant.
        $location->fresh()->update(['statut' => 1]);

        $this->assertSame('SOLDE', $location->fresh()->etatPaiement(),
            "L'argent est en caisse : la location doit se dire soldee, quel "
            . 'que soit ce que raconte le drapeau.');

        $html = $this->actingAs($valideur)->get('/locations-traitees')->getContent();
        preg_match('#' . preg_quote($location->numero, '#') . '(.*?)</tr>#s', $html, $ligne);

        $this->assertStringContainsString('Soldé', $ligne[1] ?? '',
            'La colonne affiche encore « Aucun » pour une location payee.');
    }

    /**
     * UN SEUL FORMAT DE NUMERO DE LOCATION, QUEL QUE SOIT LE CANAL.
     *
     * Le site fabriquait « 6a99b1c57161c » (uniqid), le mobile « 200952 ».
     * Le meme tableau melangeait deux ecritures, et le gestionnaire ne pouvait
     * pas dicter un numero au telephone.
     *
     * LA GARDE BALAIE TOUT app/. Ma premiere version ne regardait que deux
     * fichiers et la seule forme « $location = [ » : elle a laisse passer
     * « 'location' => [ », celle du brouillon de paiement en ligne — le
     * chemin le plus frequent, et celui qui produisait encore des uniqid.
     */
    public function test_aucune_location_ne_prend_un_numero_au_format_uniqid(): void
    {
        $fautifs = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($it as $fichier) {
            if (!$fichier->isFile() || !str_ends_with($fichier->getFilename(), '.php')) {
                continue;
            }

            $lignes = file($fichier->getPathname());

            foreach ($lignes as $i => $l) {
                if (!str_contains($l, "'numero'") || !str_contains($l, 'uniqid()')) {
                    continue;
                }

                // Les commandes, devis, livraisons et tickets ont leur propre
                // numerotation : seules les LOCATIONS sont concernees.
                $contexte = implode('', array_slice($lignes, max(0, $i - 12), 13));

                $estUneLocation = str_contains($contexte, '$location = [')
                    || str_contains($contexte, "'location' => [")
                    || str_contains($contexte, 'Location::create');

                if ($estUneLocation) {
                    $fautifs[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '',
                        $fichier->getPathname()) . ':' . ($i + 1);
                }
            }
        }

        $this->assertSame([], $fautifs,
            'Ces locations prennent un numero au format uniqid, different de '
            . 'celui du mobile : ' . implode(', ', $fautifs));

        $numero = \Help::genererNumeroUnique('location');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $numero,
            'Le numero commun doit rester six chiffres, comme ceux du mobile.');
        $this->assertLessThanOrEqual(20, strlen($numero),
            '`location.numero` est un varchar(20).');
    }

    /**
     * UNE LOCATION SOLDEE NE PROPOSE PLUS D ENCAISSER.
     *
     * Signale le 03/09/2026 : la colonne disait « Solde », et le bouton
     * « Paiement » restait a cote. J avais corrige la colonne et laisse le
     * bouton, qui lisait le meme drapeau.
     */
    public function test_une_location_soldee_ne_propose_plus_le_bouton_paiement(): void
    {
        [$caissier, $valideur] = $this->deuxAdministrateurs();
        $location = $this->uneLocationAEncaisser();

        $this->actingAs($caissier)->post(route('show.encaissements.locations.store'), [
            'numero_location' => $location->numero,
            'notes' => 'Recette : encaissement de la location',
            'mode_paiement_id' => ModePaiement::first()->id,
            'montant' => $location->montantAPayer(),
        ]);

        $paiement = \App\Models\Paiement::where('service', \Help::$LOCATION)
            ->where('service_id', $location->id)->latest('id')->first();

        $this->actingAs($valideur)
            ->post(route('show.encaissements.locations.valider', $paiement->id));

        // Drapeau reste en arriere : le cas des locations deja en base.
        $location->fresh()->update(['statut' => 1]);

        $html = $this->actingAs($valideur)->get('/locations-traitees')->getContent();
        preg_match('#' . preg_quote($location->numero, '#') . '(.*?)</tr>#s', $html, $ligne);

        $this->assertNotEmpty($ligne, 'Ligne introuvable.');
        $this->assertStringContainsString('Soldé', $ligne[1]);
        $this->assertStringNotContainsString(
            route('show.encaissements.locations', ['location' => $location->numero]),
            $ligne[1],
            'Le bouton d encaissement reste propose sur une location deja soldee.');
    }

    /**
     * AUCUNE DECISION DE PAIEMENT NE S APPUIE PLUS SUR LE DRAPEAU.
     *
     * `location.statut` est une valeur derivee rangee une seconde fois : tout
     * ce qui la lit peut mentir. Bouton d encaissement, facture FNE,
     * validation, suppression — tout doit passer par `estSoldee()` ou
     * `etatPaiement()`.
     */
    public function test_plus_aucun_ecran_ne_lit_le_drapeau_comme_un_paiement(): void
    {
        $fautifs = [];

        foreach ([
            'resources/views/gestionnaire/listeLocation.blade.php',
            'resources/views/gestionnaire/locationsTraitees.blade.php',
            'app/Http/Controllers/UserController.php',
        ] as $chemin) {
            foreach (file(base_path($chemin)) as $i => $l) {
                if (str_starts_with(ltrim($l), '//') || str_starts_with(ltrim($l), '*')) {
                    continue;
                }
                if (preg_match('/\$location->statut\s*[!=]=\s*[123]/', $l)) {
                    $fautifs[] = $chemin . ':' . ($i + 1);
                }
            }
        }

        $this->assertSame([], $fautifs,
            'Ces endroits decident d un paiement sur un drapeau qui peut etre '
            . 'reste en arriere : ' . implode(', ', $fautifs));
    }
}
