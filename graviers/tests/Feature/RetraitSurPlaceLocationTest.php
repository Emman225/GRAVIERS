<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UN RETRAIT SUR PLACE DOIT AUSSI PRODUIRE SON BON D'ENLÈVEMENT.
 *
 * Quand le client vient chercher lui-même le matériel loué, la validation ne
 * produisait RIEN : ni course, ni bon. Le client se présentait chez le
 * fournisseur sans preuve, et le fournisseur n'avait aucun bon à valider — la
 * quantité remise n'était enregistrée nulle part, et sa dette non plus.
 *
 * La crainte d'origine était fondée : créer une livraison aurait pollué la
 * tournée du livreur. Mais elle a sa réponse, et elle existait déjà dans la
 * base — la colonne `livre_par` (1 = LIVREUR, 2 = CLIENT). Les listes du
 * livreur filtrent toutes sur `livre_par = 1` : une course de retrait ne s'y
 * affiche pas. C'est exactement ce que fait la VENTE en retrait sur place
 * depuis toujours.
 *
 * Le code part au CLIENT par courriel : personne ne peut le lui remettre sur
 * place, puisqu'il n'y a pas de livreur.
 */
class RetraitSurPlaceLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function corpsDe(string $fichier, string $methode): string
    {
        $code = file_get_contents(app_path('Http/Controllers/' . $fichier . '.php'));

        // Commentaires retirés : sinon l'essai trouve les mots qu'il cherche
        // dans l'explication du correctif et passe au vert à tort.
        $code = preg_replace('!/\*.*?\*/!s', '', $code);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        $debut = strpos($code, 'function ' . $methode . '(');
        $this->assertNotFalse($debut, "$fichier::$methode introuvable.");

        $fin = strpos($code, ' function ', $debut + 20);

        return substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);
    }

    /** LA BOUCLE NE SAUTE PLUS LE RETRAIT : le bon est créé dans les deux cas. */
    public function test_le_retrait_produit_desormais_ses_lignes(): void
    {
        $corps = $this->corpsDe('UserController', 'validerLocation');

        $this->assertStringNotContainsString("? collect()", $corps,
            "La boucle est court-circuitée en retrait sur place : aucun bon "
            . "d'enlèvement ne sera créé, et le client n'aura rien à présenter "
            . 'au fournisseur.');
    }

    /** LA COURSE DE RETRAIT SE TIENT HORS DE LA TOURNÉE DU LIVREUR. */
    public function test_la_course_de_retrait_ne_pollue_pas_la_tournee(): void
    {
        $corps = $this->corpsDe('UserController', 'validerLocation');

        $this->assertStringContainsString("'livre_par'            => \$estRetrait ? 2 : 1", $corps,
            "Sans `livre_par = 2`, la course de retrait apparaîtrait dans les "
            . "listes du livreur — qui filtrent sur 1 — et lui ferait croire "
            . "qu'il a une livraison à faire.");

        $this->assertStringContainsString("'livreur_id'           => \$estRetrait ? null", $corps,
            'Un retrait sur place n’a pas de livreur : lui en affecter un le '
            . 'rendrait débiteur d’une course qu’il n’a pas faite.');
    }

    /** LE FILTRE DU LIVREUR EST BIEN CELUI QU'ON CROIT. */
    public function test_les_listes_du_livreur_filtrent_sur_livre_par(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/LivreurController.php'));

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($code, "'livre_par', 1"),
            "Les listes du livreur doivent filtrer sur `livre_par = 1`. Si ce "
            . "filtre disparaît, les courses de retrait sur place apparaîtront "
            . 'dans sa tournée.'
        );
    }

    /** C'EST LE CODE DU BON QUI PART AU CLIENT, PAS CELUI DE LA LIVRAISON. */
    public function test_le_client_recoit_le_code_du_bon(): void
    {
        $corps = $this->corpsDe('UserController', 'validerLocation');

        $this->assertStringContainsString('codeEnlevementLocation', $corps,
            "Le client doit recevoir le code de son bon d'enlèvement : sans lui, "
            . 'il ne peut pas retirer le matériel chez le fournisseur.');

        $this->assertStringContainsString("\$item['bon']", $corps,
            'Le courriel doit porter le BON, et non la livraison : le code de '
            . "validation n'a aucun sens quand aucune livraison n'aura lieu.");
    }

    /** LA CLASSE DE COURRIEL ET SON GABARIT EXISTENT. */
    public function test_le_courriel_de_retrait_est_complet(): void
    {
        $this->assertTrue(
            class_exists(\App\Mail\codeEnlevementLocation::class),
            'La classe de courriel du code de retrait est introuvable.'
        );

        $gabarit = resource_path('views/mail/code-enlevement-location.blade.php');

        $this->assertFileExists($gabarit,
            'Le gabarit du courriel manque : l’envoi échouerait à l’exécution, '
            . 'et le client n’aurait pas son code.');

        $contenu = file_get_contents($gabarit);

        $this->assertStringContainsString('code_enleve', $contenu,
            'Le courriel doit afficher le code du bon — c’est tout son objet.');
    }

    /**
     * LE FOURNISSEUR EST EXIGÉ DANS LES DEUX MODES.
     *
     * Il était traité comme une donnée de LIVRAISON : la validation ne
     * l'exigeait qu'en mode livraison, et le script du formulaire VIDAIT ses
     * sélecteurs dès qu'on choisissait le retrait. Depuis que le retrait
     * produit un bon, la boucle ne trouvait plus de fournisseur et levait une
     * exception — page blanche, erreur 500 sur /valider-location-45.
     */
    public function test_le_fournisseur_est_exige_dans_les_deux_modes(): void
    {
        $corps = $this->corpsDe('UserController', 'validerLocation');

        $this->assertStringContainsString("'fournisseur'   => 'required|array'", $corps,
            "Le fournisseur n'est exigé qu'en mode livraison : une validation "
            . 'en retrait sur place repartira sans lui, et la création du bon '
            . 'échouera.');

        $this->assertStringNotContainsString("'fournisseur.*' => 'required_if", $corps,
            "La règle conditionnelle laisse passer un retrait sans fournisseur.");
    }

    /** LE FORMULAIRE NE VIDE PLUS CES SÉLECTEURS EN RETRAIT. */
    public function test_le_formulaire_conserve_le_fournisseur_en_retrait(): void
    {
        $vue = file_get_contents(
            resource_path('views/gestionnaire/validerLocation.blade.php'));

        $this->assertStringNotContainsString(
            'champ-livraison">' . "
" . '                    <label class="form-label">' . "
"
            . '                        Fournisseur',
            $vue,
            "Le bloc fournisseur porte encore « champ-livraison » : le script le "
            . 'masquera et videra ses sélecteurs en retrait sur place.');
    }

    /**
     * LA QUANTITÉ REMISE SE SAISIT, COMME SUR L'ÉCRAN DE VENTE.
     *
     * Le fournisseur ne remet pas toujours ce qui a été commandé. C'est cette
     * quantité qui fait sa dette : elle doit être la vraie.
     */
    public function test_la_quantite_remise_est_saisissable(): void
    {
        $vue = file_get_contents(
            resource_path('views/gestionnaire/validerLocation.blade.php'));

        $this->assertStringContainsString('name="qte[{{ $ligne->id }}]"', $vue,
            "La quantité remise n'est pas saisissable : elle reste figée sur la "
            . 'quantité commandée.');

        $corps = $this->corpsDe('UserController', 'validerLocation');

        $this->assertStringContainsString("'qte.*'         => 'required|numeric|min:0.01'", $corps,
            'Une quantité nulle ou négative produirait un bon sans objet et un '
            . 'dû faux.');

        $this->assertLessThan(
            strpos($corps, "'qte'                  => \$qteRemise"),
            strpos($corps, '$qteRemise = (float)'),
            "La quantité doit être calculée AVANT son premier emploi : déclarée "
            . 'plus bas, la course partait à zéro.'
        );
    }

    /** L'ÉCRAN N'ANNONCE PLUS UN CODE DE VALIDATION QUI N'EXISTE PAS. */
    public function test_l_ecran_distingue_le_retrait(): void
    {
        $vue = file_get_contents(
            resource_path('views/gestionnaire/locationsTraitees.blade.php'));

        $this->assertStringContainsString('$course->livre_par == 2', $vue,
            "L'écran affiche un « code client » pour une course de retrait : le "
            . "gestionnaire chercherait un usage qui n'existe pas.");
    }
}
