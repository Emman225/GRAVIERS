<?php

namespace Tests\Feature;

use App\Models\Paiement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'état de parrainage, et la portabilité de sa requête.
 *
 * La requête répétait dans son GROUP BY les expressions CONCAT du SELECT.
 * MySQL 8.0.30 — celui du poste de développement — l'accepte ; le serveur de
 * production, non :
 *
 *     1055 'client.type_client' isn't in GROUP BY
 *
 * `type_client` est une des colonnes cachées dans le CASE de
 * Client::sqlNomAffiche(). La page répondait donc 200 en local et 500 en
 * ligne — un défaut qu'aucun test ne pouvait voir tant qu'il ne regardait pas
 * le SQL lui-même.
 */
class EtatParrainageTest extends TestCase
{
    public function test_la_page_repond(): void
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        URL::forceRootUrl('');
        $this->actingAs($admin)->get('/etat-de-parrainage')->assertOk();
    }

    public function test_le_group_by_ne_contient_que_des_colonnes(): void
    {
        $sql = $this->sqlDeLaRequete();

        $groupBy = substr($sql, strpos($sql, 'group by'));

        // Une expression dans le GROUP BY, et le moteur du serveur refuse la
        // requête. On n'y groupe donc que sur des colonnes nues.
        $this->assertStringNotContainsString('concat', strtolower($groupBy),
            "Le GROUP BY contient une expression : la requête ne passera pas en production.");
        $this->assertStringNotContainsString('case', strtolower($groupBy),
            "Le GROUP BY contient un CASE : la requête ne passera pas en production.");
    }

    public function test_la_requete_passe_en_mode_sql_strict(): void
    {
        $modeInitial = DB::select('SELECT @@sql_mode AS m')[0]->m;

        try {
            DB::statement("SET SESSION sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES'");

            // Ne doit lever aucune exception : c'est tout l'objet du test.
            $lignes = Paiement::statPaiementFilleule('2000-01-01', '2100-12-31');

            $this->assertNotNull($lignes);
        } finally {
            DB::statement("SET SESSION sql_mode=" . DB::getPdo()->quote($modeInitial));
        }
    }

    /** Le SQL réellement produit par la requête de l'état de parrainage. */
    private function sqlDeLaRequete(): string
    {
        DB::enableQueryLog();
        Paiement::statPaiementFilleule('2000-01-01', '2100-12-31');
        $journal = DB::getQueryLog();
        DB::disableQueryLog();

        return strtolower(end($journal)['query'] ?? '');
    }
}
