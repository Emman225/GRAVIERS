<?php

namespace Tests\Feature;

use App\Models\CoutLivraison;
use App\Models\CoutLivraisonLivreur;
use App\Models\Livreur;
use App\Models\User;
use App\Services\GrilleLivreurGenerateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * ATTRIBUER TOUTE LA GRILLE À UN LIVREUR, D'UN COUP.
 *
 * Un livreur nouvellement créé n'a AUCUNE tranche : ce qu'on lui doit se calcule
 * alors sur son ancien mode de tarification, sans marge garantie. Les 96
 * tranches du catalogue se saisissaient une à une — autant dire jamais.
 *
 * La dérivation existait, mais seulement en ligne de commande
 * (`livreur:grille --apply`), donc hors de portée du back-office. Elle vit
 * maintenant dans un service, appelé par les deux : une seule règle de
 * rémunération, sur de l'argent réellement versé.
 */
class AttribuerGrilleLivreurTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('');
    }

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    private function unLivreurSansGrille(): Livreur
    {
        $livreur = Livreur::with('user')->first();

        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur en base.');
        }

        CoutLivraisonLivreur::where('livreur_id', $livreur->id)->forceDelete();

        return $livreur;
    }

    private function nombreDeTranchesClient(): int
    {
        return GrilleLivreurGenerateur::tranchesClient()->count();
    }

    /** LES TRANCHES SONT ATTRIBUÉES, TOUTES, D'UN SEUL ENVOI. */
    public function test_le_livreur_recoit_toute_la_grille(): void
    {
        $livreur = $this->unLivreurSansGrille();
        $attendu = $this->nombreDeTranchesClient();

        if (!$attendu) {
            $this->markTestSkipped('La grille client est vide.');
        }

        $reponse = $this->actingAs($this->unAdmin())->post('/grille-livreur/pre-remplir', [
            'livreur_id' => $livreur->id,
            'part'       => 60,
            'plancher'   => 2500,
        ]);

        $reponse->assertRedirect();

        $this->assertSame($attendu,
            CoutLivraisonLivreur::where('livreur_id', $livreur->id)->count(),
            "Le livreur n'a pas reçu les $attendu tranches du catalogue.");

        $this->assertEquals(60, $livreur->fresh()->part_grille,
            'La part appliquée doit être mémorisée sur la fiche du livreur, '
            . 'sans quoi personne ne sait à quel taux la grille a été faite.');
    }

    /**
     * CHAQUE MONTANT EST LE POURCENTAGE DU TARIF CLIENT.
     *
     * C'est tout l'intérêt de la dérivation : la marge est garantie sur CHAQUE
     * tranche, et non en moyenne.
     */
    public function test_chaque_tranche_garantit_la_marge(): void
    {
        $livreur = $this->unLivreurSansGrille();

        if (!$this->nombreDeTranchesClient()) {
            $this->markTestSkipped('La grille client est vide.');
        }

        $this->actingAs($this->unAdmin())->post('/grille-livreur/pre-remplir', [
            'livreur_id' => $livreur->id,
            'part'       => 60,
            'plancher'   => 2500,
        ]);

        $posees = CoutLivraisonLivreur::where('livreur_id', $livreur->id)->get();
        $auDessus = 0;
        $faux = 0;

        foreach (GrilleLivreurGenerateur::tranchesClient() as $t) {
            $ligne = $posees->first(fn ($x) => $x->unite_produit_id == $t->unite_produit_id
                && (float) $x->unite_min === (float) $t->unite_min
                && (float) $x->distance_min_km === (float) $t->distance_min_km);

            if (!$ligne) {
                $faux++;
                continue;
            }

            $attendu = max(round((float) $t->prix_km * 0.6), min(2500.0, (float) $t->prix_km));

            if (abs($ligne->prix - $attendu) > 0.01) {
                $faux++;
            }

            if ($ligne->prix > (float) $t->prix_km + 0.01) {
                $auDessus++;
            }
        }

        $this->assertSame(0, $faux,
            'Des montants ne correspondent pas au pourcentage demandé : la '
            . 'rémunération du livreur serait fausse.');

        $this->assertSame(0, $auDessus,
            'Une tranche paie le livreur PLUS que ce que le client verse : ce '
            . 'n’est plus une marge réduite, c’est une perte.');
    }

    /**
     * LE PLANCHER NE PEUT JAMAIS DÉPASSER CE QUE LE CLIENT PAIE.
     *
     * On éprouve ici la RÈGLE, et non les données : la grille client actuelle
     * n'a aucune tranche sous 2 500 F, si bien qu'un essai posé sur elle ne
     * touche jamais ce cas. Constaté en retirant le garde-fou — rien ne
     * tombait. Le jour où une tranche à 2 000 F apparaît, DALAKOUN paierait
     * 2 500 F pour l'encaisser 2 000 : elle paierait pour livrer.
     */
    public function test_le_plancher_ne_depasse_jamais_le_prix_client(): void
    {
        // Tranche à 2 000 F, plancher demandé à 2 500 : on s'arrête à 2 000.
        $this->assertSame(2000.0,
            GrilleLivreurGenerateur::montantLivreur(2000, 60, 2500),
            'Le livreur touche PLUS que ce que le client paie : l’entreprise '
            . 'paierait pour livrer.');

        // Cas courant : 60 % de 10 000 valent 6 000, bien au-dessus du plancher.
        $this->assertSame(6000.0,
            GrilleLivreurGenerateur::montantLivreur(10000, 60, 2500),
            'Le pourcentage doit s’appliquer dès qu’il dépasse le plancher.');

        // Le plancher relève : 60 % de 3 000 valent 1 800, relevés à 2 500.
        $this->assertSame(2500.0,
            GrilleLivreurGenerateur::montantLivreur(3000, 60, 2500),
            'Le plancher doit relever le montant quand le pourcentage est en '
            . 'dessous.');

        // Sans plancher, le pourcentage seul.
        $this->assertSame(1800.0,
            GrilleLivreurGenerateur::montantLivreur(3000, 60, null),
            'Sans plancher, seul le pourcentage s’applique.');
    }

    /**
     * UNE GRILLE DÉJÀ REMPLIE NE S'ÉCRASE PAS SANS LE DIRE.
     *
     * Le remplissage efface d'abord tout. Lancé par mégarde sur un livreur dont
     * les tranches ont été ajustées à la main, il les perdrait toutes.
     */
    public function test_une_grille_existante_n_est_pas_ecrasee_en_silence(): void
    {
        $livreur = $this->unLivreurSansGrille();

        if (!$this->nombreDeTranchesClient()) {
            $this->markTestSkipped('La grille client est vide.');
        }

        $admin = $this->unAdmin();

        // Premier remplissage : la grille existe désormais.
        $this->actingAs($admin)->post('/grille-livreur/pre-remplir', [
            'livreur_id' => $livreur->id, 'part' => 60, 'plancher' => 2500,
        ]);

        $avant = CoutLivraisonLivreur::where('livreur_id', $livreur->id)
            ->orderBy('id')->pluck('prix')->all();

        // Second envoi, SANS cocher « remplacer » : rien ne doit bouger.
        $this->actingAs($admin)->post('/grille-livreur/pre-remplir', [
            'livreur_id' => $livreur->id, 'part' => 20, 'plancher' => 0,
        ]);

        $apres = CoutLivraisonLivreur::where('livreur_id', $livreur->id)
            ->orderBy('id')->pluck('prix')->all();

        $this->assertSame($avant, $apres,
            'La grille a été refaite sans qu’on l’ait demandé : les tranches '
            . 'ajustées à la main sont perdues.');
    }

    /** MAIS ELLE S'ÉCRASE QUAND ON LE DEMANDE. */
    public function test_la_grille_se_refait_quand_on_le_demande(): void
    {
        $livreur = $this->unLivreurSansGrille();

        if (!$this->nombreDeTranchesClient()) {
            $this->markTestSkipped('La grille client est vide.');
        }

        $admin = $this->unAdmin();

        $this->actingAs($admin)->post('/grille-livreur/pre-remplir', [
            'livreur_id' => $livreur->id, 'part' => 60, 'plancher' => 2500,
        ]);
        $avant = CoutLivraisonLivreur::where('livreur_id', $livreur->id)->sum('prix');

        $this->actingAs($admin)->post('/grille-livreur/pre-remplir', [
            'livreur_id' => $livreur->id, 'part' => 30, 'plancher' => null,
            'remplacer'  => 1,
        ]);
        $apres = CoutLivraisonLivreur::where('livreur_id', $livreur->id)->sum('prix');

        $this->assertNotEquals($avant, $apres,
            'La grille n’a pas été refaite alors que le remplacement était demandé.');

        $this->assertSame($this->nombreDeTranchesClient(),
            CoutLivraisonLivreur::where('livreur_id', $livreur->id)->count(),
            'Le remplacement doit laisser exactement une tranche par tranche '
            . 'client — ni doublon, ni oubli.');
    }

    /** UN IDENTIFIANT INCONNU EST REFUSÉ, SANS RIEN ÉCRIRE. */
    public function test_un_identifiant_inconnu_est_refuse(): void
    {
        $inexistant = (Livreur::max('id') ?? 0) + 1000;
        $avant = CoutLivraisonLivreur::count();

        $reponse = $this->actingAs($this->unAdmin())->post('/grille-livreur/pre-remplir', [
            'livreur_id' => $inexistant, 'part' => 60,
        ]);

        $reponse->assertSessionHasErrors('livreur_id');

        $this->assertSame($avant, CoutLivraisonLivreur::count(),
            'Une tranche a été écrite alors que le livreur n’existe pas.');
    }

    /** UNE PART HORS BORNES EST REFUSÉE. */
    public function test_une_part_hors_bornes_est_refusee(): void
    {
        $livreur = $this->unLivreurSansGrille();

        foreach ([0, 100, 150] as $part) {
            $reponse = $this->actingAs($this->unAdmin())->post('/grille-livreur/pre-remplir', [
                'livreur_id' => $livreur->id, 'part' => $part,
            ]);

            $reponse->assertSessionHasErrors('part');
        }

        $this->assertSame(0, CoutLivraisonLivreur::where('livreur_id', $livreur->id)->count(),
            'Une grille a été écrite avec une part impossible.');
    }

    /**
     * LE CALCUL NE VIT QU'À UN SEUL ENDROIT.
     *
     * La commande `livreur:grille` et l'écran doivent appliquer la MÊME règle :
     * deux copies auraient divergé au premier ajustement.
     */
    public function test_la_commande_et_l_ecran_partagent_le_meme_calcul(): void
    {
        $commande = file_get_contents(app_path('Console/Commands/RemplirGrilleLivreur.php'));

        $this->assertStringContainsString(
            'GrilleLivreurGenerateur::montantLivreur', $commande,
            'La commande recalcule le montant de son côté : deux règles de '
            . 'rémunération finiraient par diverger.');
    }

    /** L'ÉCRAN PORTE LE FORMULAIRE, ET SES GARDE-FOUS. */
    public function test_l_ecran_porte_le_formulaire(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/grille-tarifaire')->getContent();

        $this->assertStringContainsString('grille-livreur/pre-remplir', $html,
            'Le formulaire d’attribution est absent de la grille tarifaire.');

        foreach (['name="livreur_id"', 'name="part"', 'name="plancher"', 'name="remplacer"'] as $champ):
            $this->assertStringContainsString($champ, $html,
                "Le champ « $champ » manque au formulaire.");
        endforeach;
    }

    /** L'IDENTIFIANT SE LIT ET SE COPIE DEPUIS LA LISTE DES LIVREURS. */
    public function test_l_identifiant_du_livreur_est_copiable(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/livreur/list')->getContent();

        $entetes = [];
        if (preg_match('/<thead.*?<\/thead>/s', $html, $t)
            && preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $t[0], $c)) {
            $entetes = array_map(fn ($x) => trim(strip_tags($x)), $c[1]);
        }

        $position = array_search('ID livreur', $entetes, true);

        $this->assertNotFalse($position, 'La colonne « ID livreur » est absente.');

        $this->assertSame('Action', $entetes[$position + 1] ?? null,
            'La colonne « ID livreur » doit précéder immédiatement « Action ».');

        $this->assertStringContainsString('js-copier-id', $html,
            'La valeur n’est pas copiable : rien ne déclenche la copie.');

        $this->assertStringContainsString('navigator.clipboard', $html,
            'Le script de copie n’est pas rendu.');

        // Repli indispensable : `navigator.clipboard` n'existe QUE sur une
        // origine sûre. Un accès en http laisserait le bouton sans effet.
        $this->assertStringContainsString("execCommand('copy')", $html,
            'Sans repli, la copie ne marcherait pas hors https.');
    }
}
