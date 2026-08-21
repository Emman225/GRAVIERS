<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deux colonnes de ligne_paiement étaient trop courtes pour ce que le code y
 * écrit :
 *
 *   moyen_paiement — varchar(30), alors qu'on y recopie le libellé du mode de
 *     paiement. « En Agence (virement, Chèque, Espèce) » en fait 36. Tout
 *     encaissement réglé par ce mode échouait donc, avec une erreur SQL en
 *     pleine transaction — ou, sur un serveur en mode permissif, un libellé
 *     tronqué au milieu d'un mot sur le reçu remis au client.
 *
 *   reference — varchar(20), alors que la validation du formulaire accepte
 *     jusqu'à 80 caractères. Une référence de transaction un peu longue passait
 *     toutes les vérifications avant d'être refusée par la base.
 *
 * Dans les deux cas, le résultat dépendait du mode SQL du serveur plutôt que du
 * code. Les colonnes sont portées à la taille de ce qu'on y met réellement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ligne_paiement MODIFY moyen_paiement VARCHAR(100) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE ligne_paiement MODIFY reference VARCHAR(80) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Rétrécir suppose de ne plus rien avoir de trop long, sinon MySQL
        // refuse la modification.
        DB::statement('UPDATE ligne_paiement SET moyen_paiement = LEFT(moyen_paiement, 30) WHERE CHAR_LENGTH(moyen_paiement) > 30');
        DB::statement('UPDATE ligne_paiement SET reference = LEFT(reference, 20) WHERE CHAR_LENGTH(reference) > 20');
        DB::statement('ALTER TABLE ligne_paiement MODIFY moyen_paiement VARCHAR(30) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE ligne_paiement MODIFY reference VARCHAR(20) NULL DEFAULT NULL');
    }
};
