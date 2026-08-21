<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garantit qu'un mode « Paiement en agence » actif est proposé au client.
 *
 * Depuis que le choix du client se limite au paiement en ligne et au règlement
 * sur place (ModePaiement::listePourClient()), ce mode est le SEUL qui permette
 * encore de commander à crédit. S'il manque, un client à terme ne peut plus
 * commander du tout.
 *
 * On travaille par libellé et non par id : celui de la ligne « Paiement en
 * agence » diffère entre le local et la production. L'id 1, mode historique
 * « En Agence (virement, Chèque, Espèce) », est écarté — il ne doit plus être
 * proposé nulle part et ne sert qu'à l'historique des paiements déjà saisis.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Déjà en place : on ne touche à rien, pour ne pas créer un doublon
        // qui apparaîtrait deux fois dans la liste du client.
        $dejaActif = DB::table('mode_paiement')
            ->where('id', '!=', 1)
            ->where('libelle', 'like', '%agence%')
            ->whereNull('deleted_at')
            ->where('statut', Help::$STATUT_ACTIF)
            ->exists();

        if ($dejaActif) {
            return;
        }

        // Une ligne existe mais a été désactivée ou supprimée au back-office :
        // on la rétablit plutôt que d'en créer une seconde, afin que les
        // paiements qui la référencent gardent leur libellé.
        $aRetablir = DB::table('mode_paiement')
            ->where('id', '!=', 1)
            ->where('libelle', 'like', '%agence%')
            ->orderByDesc('id')
            ->first();

        if ($aRetablir) {
            DB::table('mode_paiement')
                ->where('id', $aRetablir->id)
                ->update([
                    'statut'     => Help::$STATUT_ACTIF,
                    'en_ligne'   => 0,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('mode_paiement')->insert([
            'libelle'     => 'Paiement en agence',
            'description' => "Le client règle sa commande sur place, à l'agence.",
            'statut'      => Help::$STATUT_ACTIF,
            'en_ligne'    => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Volontairement sans effet.
     *
     * Redésactiver ce mode priverait les clients à terme de toute possibilité de
     * commander et rendrait orphelines les commandes qui le référencent : ce
     * n'est pas un retour en arrière sûr. Pour le retirer, passer par le
     * back-office, en connaissance de cause.
     */
    public function down(): void
    {
    }
};
