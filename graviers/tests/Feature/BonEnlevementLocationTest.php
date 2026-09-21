<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE LOCATION DOIT PRODUIRE UN BON D'ENLÈVEMENT, COMME UNE VENTE.
 *
 * Constaté le 28/08/2026 : valider une location crée bien les courses, et le
 * client reçoit son code de validation — mais AUCUN bon d'enlèvement n'était
 * émis. Le livreur se présentait chez le fournisseur sans rien à lui montrer,
 * alors que la vente lui donne un code depuis toujours.
 *
 * La plomberie existait déjà : la liste du livreur joint `enlevement` en
 * leftJoin et lit `code_enleve`, et son écran « Bons d'enlèvement » les
 * affiche. Il ne manquait que la ligne.
 *
 * LE MONTANT PORTE LA DURÉE. La dette d'un enlèvement vaut
 * `quantité × prix_fournisseur` (Enlevement::montantDu) : la notion de jours
 * n'y existe pas. On inscrit donc dans `prix_fournisseur` le prix d'achat
 * multiplié par le nombre de jours, de sorte que le dû vaille bien
 * `prix d'achat × quantité × jours` — la règle arrêtée le 28/08/2026.
 */
class BonEnlevementLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function controleur(): string
    {
        $source = file_get_contents(app_path('Http/Controllers/UserController.php'));

        // Les commentaires sont retirés : sans cela, un essai trouve les mots
        // qu'il cherche dans l'explication du correctif et passe au vert alors
        // que le code exécuté a disparu.
        $source = preg_replace('!/\*.*?\*/!s', '', $source);
        $source = preg_replace('!^\s*//.*$!m', '', $source);

        $debut = strpos($source, 'function validerLocation(');

        return substr($source, $debut, strpos($source, 'public function', $debut + 10) - $debut);
    }

    /** LE BON EST CRÉÉ, ET IL PORTE UN CODE. */
    public function test_la_validation_cree_un_bon_avec_son_code(): void
    {
        $code = $this->controleur();

        $this->assertStringContainsString('Enlevement::create([', $code,
            "Aucun bon d'enlèvement n'est créé : le livreur n'a rien à montrer au fournisseur.");

        $this->assertStringContainsString("'code_enleve'      => \$this->generateCode(),", $code,
            'Le bon est créé sans code : le fournisseur ne peut pas le vérifier.');

        $this->assertStringContainsString("'livraison_id'     => \$liv->id,", $code,
            'Le bon doit être rattaché à la course qui vient d\'être créée, sans quoi '
            . 'ni le livreur ni le back-office ne le retrouvent.');
    }

    /** LE PRIX PORTE LE NOMBRE DE JOURS — c'est toute la règle de la location. */
    public function test_le_montant_du_bon_tient_compte_de_la_duree(): void
    {
        $code = $this->controleur();

        $this->assertStringContainsString("'prix_fournisseur' => \$prixAchat * \$jours,", $code,
            "Le bon ne facturerait qu'une journée : le fournisseur serait payé "
            . "pour un jour un matériel loué une semaine.");

        $this->assertStringContainsString("\$jours = max(1, (int) (\$detail->nombre_jour ?? 1));", $code,
            'Une ligne sans durée donnerait un dû de zéro. Le plancher à un jour '
            . "évite un bon qui ne vaut rien — le défaut déjà corrigé sur les ventes.");
    }

    /** LE PRIX EST CELUI DU FOURNISSEUR DÉSIGNÉ, pas celui du catalogue. */
    public function test_le_prix_vient_du_fournisseur_retenu(): void
    {
        $code = $this->controleur();

        $this->assertStringContainsString("->where('fournisseur_id', \$fournisseurLigne)", $code,
            "Le prix d'achat doit être lu chez le fournisseur DÉSIGNÉ : lire "
            . "ailleurs ferait payer à l'un le tarif d'un autre.");
    }

    /** UNE LIGNE SANS FOURNISSEUR N'EST PAS VALIDÉE À MOITIÉ. */
    public function test_une_ligne_sans_fournisseur_arrete_la_validation(): void
    {
        $code = $this->controleur();

        // LE REFUS DOIT ÊTRE UN MESSAGE, PAS UNE PAGE BLANCHE.
        //
        // Cet essai exigeait une RuntimeException et une règle
        // « required_if:mode_livraison,livraison ». Les deux étaient le défaut :
        // l'exception remontait en erreur 500 sur /valider-location-45, et la
        // règle conditionnelle laissait partir un retrait sur place sans
        // fournisseur — alors que le retrait produit lui aussi un bon.
        //
        // Ce qui compte reste le même : une ligne non désignée n'est pas
        // validée à moitié.
        $this->assertStringContainsString('ValidationException', $code,
            'Sans garde-fou, une ligne non désignée créerait une course sans bon : '
            . "le livreur partirait sans code, et personne ne le saurait. Et ce "
            . 'refus doit se lire à l’écran, non se solder par une page blanche.');

        $this->assertStringContainsString("'fournisseur.*' => 'required|integer|exists:fournisseur,id'", $code,
            'La désignation doit être exigée à la validation du formulaire, dans '
            . 'les DEUX modes : le retrait sur place aussi produit un bon.');
    }

    /** LE FORMULAIRE PROPOSE LE CHOIX, ligne par ligne. */
    public function test_le_formulaire_demande_le_fournisseur_de_chaque_ligne(): void
    {
        $vue = file_get_contents(resource_path('views/gestionnaire/validerLocation.blade.php'));

        $this->assertStringContainsString('name="fournisseur[{{ $ligne->id }}]"', $vue,
            'Le formulaire ne permet pas de désigner le fournisseur : les produits '
            . 'de location en comptent jusqu\'à cinq, le choix ne peut pas être deviné.');

        $this->assertStringContainsString('champ-livraison', substr($vue, 0, strpos($vue, 'name="fournisseur')),
            "Le bloc doit porter « champ-livraison » : en retrait sur place aucune "
            . "livraison n'est créée, donc aucun bon, et un champ obligatoire masqué "
            . "bloquerait l'envoi sans message.");
    }

    /**
     * LE BACK-OFFICE NE MONTRE PLUS LES CODES (demande du 10/09/2026) : le code
     * de livraison est au client, le bon d'enlèvement au livreur ou au client
     * en retrait, chacun le lit chez lui. « Locations traitées » garde l'état
     * de la course et de l'enlèvement (validation du fournisseur, quantité
     * servie), pas les codes.
     */
    public function test_le_back_office_ne_montre_plus_les_codes(): void
    {
        $vue = file_get_contents(resource_path('views/gestionnaire/locationsTraitees.blade.php'));

        $this->assertStringNotContainsString('{{ $course->numero }}', $vue, 'Le code de livraison ne doit plus être affiché.');
        $this->assertStringNotContainsString('{{ $refusee->numero }}', $vue, 'Même refusé, un code ne s\'affiche plus.');
        $this->assertStringNotContainsString('bon n°', $vue, 'Le numéro du bon ne doit plus être affiché.');
        $this->assertStringContainsString('Livraison / enlèvement', $vue);
        $this->assertStringContainsString('$course->etatLisible()', $vue, 'L\'état de la course reste visible.');
        $this->assertStringContainsString('$bon->libelleValidationFournisseur()', $vue, 'L\'état de l\'enlèvement reste visible.');
    }

    /**
     * LA MARGE DE DALAKOUN RÉSISTE À LA DURÉE.
     *
     * Le client paie le prix de vente — prix d'achat le PLUS ÉLEVÉ majoré du
     * pourcentage DALAKOUN — multiplié par la quantité et les jours. Le
     * fournisseur, lui, reçoit SON prix d'achat, multiplié par les mêmes
     * quantité et jours. Les deux côtés portent le même facteur : le TAUX de
     * marge ne dépend donc pas de la durée, seul son montant grandit.
     */
    public function test_la_marge_ne_depend_pas_de_la_duree(): void
    {
        $achatLePlusEleve = 8000.0;   // fournisseur le plus cher
        $achatDuServant   = 7500.0;   // celui qui remet réellement le matériel
        $taux             = 10.0;     // pourcentage DALAKOUN

        $prixDeVente = $achatLePlusEleve * (1 + $taux / 100);

        foreach ([1, 7, 30] as $jours) {
            $quantite = 2.0;

            $encaisse = $prixDeVente * $quantite * $jours;
            $reverse  = $achatDuServant * $quantite * $jours;
            $marge    = $encaisse - $reverse;

            $this->assertEqualsWithDelta(
                ($prixDeVente - $achatDuServant) / $prixDeVente,
                $marge / $encaisse,
                0.0001,
                "Le taux de marge change avec la durée ($jours jours) : ce serait "
                . 'le signe que les deux côtés ne portent pas le même facteur.'
            );

            $this->assertGreaterThan(
                $achatLePlusEleve * $quantite * $jours * ($taux / 100) - 0.01,
                $marge,
                'La marge est tombée sous le pourcentage voulu : retenir le prix '
                . "d'achat le plus élevé pour la vente doit la garantir quel que "
                . 'soit le fournisseur qui sert.'
            );
        }
    }
}
