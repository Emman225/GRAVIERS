<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UNE COMMISSION DE VENTE NE DOIT PAS S'ÉTIQUETER « LOCATION ».
 *
 * `commission_apporteur.commande_id` est POLYMORPHE : selon `type_affaire`, il
 * désigne une COMMANDE ou une LOCATION. L'un des trois points de création — le
 * règlement au guichet — ne renseignait pas la colonne. Elle est NOT NULL sans
 * valeur par défaut, et MySQL retient alors la PREMIÈRE valeur de
 * l'énumération : 'LOCATION'.
 *
 * Constaté le 28/08/2026 sur l'application apporteur : « null null (# null) —
 * Total : 0 F ». Le montant de la commission restait juste, puisqu'il vit sur
 * la ligne elle-même ; seuls le client et le montant de l'affaire manquaient,
 * la jointure cherchant dans la mauvaise table.
 */
class CommissionTypeAffaireTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * LA DÉMONSTRATION DU DÉFAUT : omettre la colonne ne laisse pas un vide,
     * elle inscrit un type FAUX.
     */
    public function test_omettre_le_type_inscrit_location_et_non_un_vide(): void
    {
        $commande = DB::table('commande')->first();
        $apporteur = Apporteur::first();

        if (!$commande || !$apporteur) {
            $this->markTestSkipped('Base de travail sans commande ou sans apporteur.');
        }

        $commission = new CommissionApporteur;
        $commission->commande_id = $commande->id;
        $commission->apporteur_id = $apporteur->id;
        $commission->montant = 100;
        $commission->save();

        $this->assertSame(
            'LOCATION',
            DB::table('commission_apporteur')->where('id', $commission->id)->value('type_affaire'),
            "Si cette valeur n'est plus 'LOCATION', l'énumération ou le défaut de "
            . 'la colonne a changé : la démonstration ci-dessous ne vaut plus, '
            . 'et le correctif doit être revu.'
        );
    }

    /** LE POINT DE CRÉATION FAUTIF RENSEIGNE DÉSORMAIS LE TYPE. */
    public function test_le_reglement_au_guichet_renseigne_le_type_affaire(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/PaiementController.php'));

        // Commentaires retirés : sinon l'essai trouve les mots qu'il cherche
        // dans l'explication du correctif et passe au vert à tort.
        $code = preg_replace('!/\*.*?\*/!s', '', $source);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        $debut = strpos($code, 'function addCommission(');
        $fin   = strpos($code, 'function ', $debut + 20);
        $bloc  = substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);

        $this->assertStringContainsString('$commission->type_affaire', $bloc,
            "La commission part sans type d'affaire : elle sera enregistrée en "
            . "'LOCATION' et son client restera introuvable.");

        // La décision a QUITTÉ ce bloc le 01/09/2026 : elle vit désormais dans
        // App\Support\AffaireCommissionnable, partagée par les trois chemins de
        // règlement (agence, en ligne, mobile). L'essai visait la forme d'alors
        // — un ternaire sur Help::$LOCATION — et non son intention, qui est que
        // le type soit DÉDUIT du service réglé et jamais écrit en dur.
        $this->assertStringContainsString(
            'AffaireCommissionnable::typeSiRattachable($service', $bloc,
            'Le type doit être DÉDUIT du service réglé, par la règle partagée : '
            . 'un règlement de location produirait sinon une commission de vente, '
            . 'et une demande de livraison une commission sans affaire.');

        $this->assertStringNotContainsString("'VENTE'", $bloc,
            'Le type est écrit en dur dans le bloc : il ne suit plus le service '
            . 'réglé.');
    }

    /**
     * LA CIBLE SE RETROUVE SUR LA FACTURE QUAND LE PAIEMENT NE LA PORTE PAS.
     *
     * Constaté en production le 28/08/2026 : trois commissions portaient
     * `commande_id = NULL`. La chaîne : le règlement porte sur une FACTURE,
     * `facture.service_id` est nullable, le paiement hérite de ce vide, et la
     * commission avec lui — d'où « null null (# null) — Total : 0 F ».
     */
    public function test_la_cible_se_replie_sur_la_facture(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/PaiementController.php'));
        $code = preg_replace('!/\*.*?\*/!s', '', $source);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        $debut = strpos($code, 'function addCommission(');
        $fin   = strpos($code, 'function ', $debut + 20);
        $bloc  = substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);

        $this->assertStringContainsString('optional($facture)->service_id', $bloc,
            "La cible ne se replie pas sur la facture : une facture sans "
            . '`service_id` produira encore une commission sans affaire.');

        $this->assertStringContainsString('\Log::warning', $bloc,
            "Une commission sans cible doit laisser une trace : sinon elle "
            . "réapparaît indéfiniment en « null null » sans que personne ne "
            . 'sache pourquoi.');
    }

    /** LE CHAÎNON QUI MANQUAIT : la relation vers la facture. */
    public function test_le_paiement_sait_atteindre_sa_facture(): void
    {
        $paiement = \App\Models\Paiement::first();

        if (!$paiement) {
            $this->markTestSkipped('Base de travail sans paiement.');
        }

        $this->assertSame(
            \App\Models\Facture::class,
            get_class($paiement->facture()->getRelated()),
            "`facture_id` était renseigné depuis longtemps, mais aucune relation "
            . "ne permettait de le suivre : le repli restait inerte."
        );
    }

    /**
     * LE GUICHET NE DÉDUIT PLUS LE TYPE DU PRODUIT.
     *
     * `crediterApporteur` lisait `produit.type_affaire` — colonne qui classe le
     * CATALOGUE, pas la nature de l'affaire. Les deux emploient le même
     * vocabulaire, ce qui rendait l'erreur invisible. Une commande portant un
     * article de location, ou dont la chaîne de relations rendait null,
     * produisait une commission étiquetée LOCATION alors que `commande_id`
     * désigne une COMMANDE.
     *
     * Constaté en production le 28/08/2026 : commission n° 44 sur la commande
     * 138 (client 54, 192 500 F), affichée « null null — Total : 0 F ».
     */
    public function test_le_guichet_n_etiquette_plus_selon_le_produit(): void
    {
        // La commission du guichet vit dans App\Services\ReglementValide depuis
        // le 07/09/2026 (règle partagée avec les avances clients).
        $source = file_get_contents(app_path('Services/ReglementValide.php'));
        $code = preg_replace('!/\*.*?\*/!s', '', $source);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        $debut = strpos($code, 'function crediterApporteur(');
        $fin   = strpos($code, 'function ', $debut + 20);
        $bloc  = substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);

        $this->assertStringNotContainsString('produit?->type_affaire', $bloc,
            "Le type d'affaire est encore déduit du produit : une commande "
            . "portant un article de location produira une commission que la "
            . 'jointure ira chercher dans la mauvaise table.');

        $this->assertStringContainsString("'type_affaire' => 'VENTE'", $bloc,
            "`commande_id` reçoit ici un identifiant de COMMANDE : le type ne "
            . "se déduit pas, il vaut 'VENTE'.");
    }

    /** LES TROIS POINTS DE CRÉATION RENSEIGNENT LE TYPE. */
    public function test_aucun_point_de_creation_n_omet_le_type(): void
    {
        $fichiers = [
            app_path('Http/Controllers/PaiementController.php'),
            app_path('Http/Controllers/PaiementEnLigne.php'),
            app_path('Http/Controllers/CommandeComptantController.php'),
            app_path('Http/Controllers/LocationComptantController.php'),
            app_path('Services/ReglementValide.php'),
            app_path('Services/Avances.php'),
        ];

        foreach ($fichiers as $fichier) {
            $code = file_get_contents($fichier);

            if (!str_contains($code, 'CommissionApporteur')) {
                continue;
            }

            $this->assertStringContainsString(
                'type_affaire',
                $code,
                basename($fichier) . " crée une commission sans jamais nommer "
                . "`type_affaire` : elle partira en 'LOCATION'."
            );
        }
    }

    /** LA REPRISE NE TOUCHE QUE CE QUI EST CERTAIN. */
    public function test_la_reprise_epargne_les_cas_ambigus(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_08_28_150000_corriger_le_type_affaire_des_commissions.php'
        ));

        $this->assertStringContainsString('whereExists', $migration,
            "La reprise doit exiger que la COMMANDE existe.");

        $this->assertStringContainsString('whereNotExists', $migration,
            "La reprise doit ÉCARTER les identifiants qui désignent aussi une "
            . "location : les rebasculer fausserait une commission juste.");
    }
}
