<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UN POINT DE FIDÉLITÉ SE GAGNE, IL NE S'OFFRE PAS.
 *
 * Constaté le 27/08/2026 : un client passe une première commande, choisit le
 * règlement en agence, ne se présente jamais au guichet — et convertit malgré
 * tout un point de fidélité sur sa commande suivante. La remise est bien
 * accordée ; l'encaissement qui aurait dû la financer n'a jamais eu lieu.
 *
 * La cause n'était pas dans l'attribution — les deux guichets créditent
 * correctement, à la VALIDATION de l'encaissement — mais dans la table : la
 * colonne `client.point` naissait avec une valeur par défaut de 1. Chaque
 * inscription offrait donc dix francs de remise.
 *
 * Ces essais tiennent les deux bouts de la règle : rien à l'inscription, et
 * un crédit sur CHAQUE canal de règlement — y compris celui du site en ligne,
 * qui n'en donnait aucun.
 */
class PointGagneEtNonOffertTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Un client tout neuf, créé comme le ferait une inscription : on n'écrit
     * PAS la colonne `point`, puisque c'est précisément sa valeur par défaut
     * que ces essais contrôlent.
     */
    private function unClient(): Client
    {
        $user = User::create([
            'nom_prenoms'  => 'Essai fidelite',
            'email'        => 'fid-' . uniqid() . '@example.test',
            'login'        => 'fid' . substr((string) uniqid(), -8),
            'password'     => bcrypt(\Illuminate\Support\Str::random(16)),
            'contact'      => '0700000000',
            'type_user_id' => 4,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        // La colonne `point` n'est VOLONTAIREMENT pas renseignee : c'est sa
        // valeur par defaut que ces essais controlent.
        $client = Client::create([
            'user_id'     => $user->id,
            'nom'         => 'Essai',
            'prenom'      => 'Fidelite',
            'email'       => $user->email,
            'contact1'    => '0700000000',
            'type_client' => 'PARTICULIER',
        ]);

        return $client->fresh();
    }

    /** LE POINT DE DÉPART D'UN NOUVEAU CLIENT EST ZÉRO. */
    public function test_une_inscription_ne_donne_aucun_point(): void
    {
        $client = $this->unClient();

        $this->assertSame(
            0.0,
            (float) $client->point,
            "Un client fraîchement inscrit démarre avec un point : c'est le "
            . "défaut d'origine, et il suffit à financer une remise sur la "
            . "commande suivante sans qu'un franc soit entré en caisse."
        );
    }

    /** La valeur par défaut de la colonne elle-même, et non le seul modèle. */
    public function test_la_colonne_ne_porte_plus_de_valeur_offerte(): void
    {
        $defaut = DB::selectOne(
            "SELECT COLUMN_DEFAULT AS d FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client'
                AND COLUMN_NAME = 'point'"
        );

        $this->assertSame(
            0.0,
            (float) $defaut->d,
            'La colonne offre encore un point : une insertion qui ne précise '
            . 'pas la colonne — un import, un script — le redonnerait.'
        );
    }

    /**
     * LE POINT DÉJÀ OFFERT EST REPRIS, MAIS SEULEMENT LÀ OÙ IL EST CERTAIN.
     *
     * Un client qui a réellement encaissé porte un solde dont on ne peut pas
     * dire quelle part vient du cadeau : on n'y touche pas.
     */
    public function test_le_point_offert_est_repris_sauf_si_le_client_a_deja_paye(): void
    {
        $jamaisPaye = $this->unClient();
        $jamaisPaye->update(['point' => 1]);

        $aDejaPaye = $this->unClient();
        $aDejaPaye->update(['point' => 1]);

        $paiement = new Paiement;
        $paiement->client_id = $aDejaPaye->id;
        $paiement->statut = 1;
        $paiement->montant_total = 5000;
        // Les trois colonnes obligatoires de la table.
        $paiement->code = 'ESSAI-' . substr((string) uniqid(), -12);
        $paiement->libelle = 'Essai fidelite';
        $paiement->save();

        // La requête de la migration, à l'identique.
        DB::table('client')
            ->where('point', 1)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('paiement')
                  ->whereColumn('paiement.client_id', 'client.id')
                  ->where('paiement.statut', 1);
            })
            ->update(['point' => 0]);

        $this->assertSame(0.0, (float) $jamaisPaye->fresh()->point,
            "Le point offert n'a pas été repris chez un client qui n'a jamais réglé.");

        $this->assertSame(1.0, (float) $aDejaPaye->fresh()->point,
            "Le solde d'un client qui a déjà encaissé a été touché : on ne peut "
            . "pas distinguer ce qui vient du cadeau de ce qui vient de son "
            . "règlement, donc on n'y touche pas.");
    }

    /**
     * LE RÈGLEMENT EN LIGNE DEPUIS LE SITE DOIT CRÉDITER COMME LES AUTRES.
     *
     * Les deux guichets créditaient, l'application mobile aussi — le site, non.
     * Un client réglant 500 000 F par carte depuis le site ne gagnait pas un
     * point, quand le même règlement au guichet lui en rapportait 500.
     */
    public function test_le_canal_en_ligne_du_site_credite_les_points(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ClientController.php'));

        // LES COMMENTAIRES SONT RETIRES AVANT TOUTE VERIFICATION.
        //
        // Sans cela, l'essai trouvait « Help::pointsPour » dans le commentaire
        // qui explique le correctif, et passait au vert alors que le credit
        // avait ete neutralise. Un essai qui se contente d'un mot ne controle
        // pas le code execute.
        $codeSeul = preg_replace('!/\*.*?\*/!s', '', $source);
        $codeSeul = preg_replace('!^\s*//.*$!m', '', $codeSeul);

        $confirmation = substr($codeSeul, strpos($codeSeul, 'function verifiePaiement'));

        $this->assertStringContainsString(
            '$points = Help::pointsPour((float) $paiement->montant_total);',
            $confirmation,
            "La confirmation d'un paiement en ligne n'attribue aucun point : le "
            . "canal décide encore de la récompense."
        );

        $this->assertStringContainsString(
            "Schema::hasColumn('paiement', 'points_attribues')",
            $confirmation,
            'Le garde-fou de schéma manque : sur une base où la migration du '
            . "25/08 n'est pas passée, le client verrait une page blanche après "
            . 'avoir payé.'
        );

        $this->assertStringContainsString(
            'use Illuminate\Support\Facades\Schema;',
            $codeSeul,
            "Schema n'est pas importé : l'appel se résoudrait en "
            . 'App\Http\Controllers\Schema — erreur fatale, et seulement au '
            . "retour d'un client depuis la passerelle."
        );
    }

    /** La règle de calcul reste celle de partout, et elle est proportionnelle. */
    public function test_la_regle_de_calcul_est_inchangee(): void
    {
        Configuration::first()->update(['montant_pour_un_point' => 1000]);

        $this->assertSame(0, \Help::pointsPour(999));
        $this->assertSame(1, \Help::pointsPour(1000));
        $this->assertSame(28, \Help::pointsPour(28813));
    }
}
