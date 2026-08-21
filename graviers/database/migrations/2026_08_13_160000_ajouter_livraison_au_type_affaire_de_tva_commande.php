<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * tva_commande.type_affaire doit accepter « LIVRAISON ».
     *
     * La colonne a été créée par la migration du 05/12/2024, qui tirait ses
     * valeurs de Help::listeTypeAffaire() — laquelle ne renvoie que LOCATION et
     * VENTE. L'ENUM s'est donc figé sur ces deux valeurs.
     *
     * Or la validation d'une demande de livraison écrit Help::$LIVRAISON dans
     * cette colonne (ClientController::valideDemande). En base stricte, l'ENUM
     * refuse la valeur : « 1265 Data truncated for column 'type_affaire' ». La
     * demande et ses lignes étaient déjà enregistrées, l'écriture de la TVA
     * échouait, et le client recevait une page blanche.
     *
     * Ce décalage ne se voyait pas en développement : la base locale portait
     * déjà LIVRAISON, ajouté à la main et jamais consigné dans une migration.
     * D'où un parcours qui passait ici et échouait sur le serveur.
     *
     * La colonne est reconstruite en conservant sa nullabilité d'origine : elle
     * diffère peut-être d'un environnement à l'autre, et la forcer romprait des
     * insertions qui ne la renseignent pas.
     */
    public function up(): void
    {
        $colonne = $this->colonne();

        if (!$colonne) {
            return; // Colonne absente : rien à modifier.
        }

        if (str_contains($colonne->COLUMN_TYPE, "'LIVRAISON'")) {
            return; // Déjà en place (cas des bases de développement).
        }

        DB::statement($this->requeteModification($colonne, "ENUM('LOCATION','VENTE','LIVRAISON')"));
    }

    public function down(): void
    {
        $colonne = $this->colonne();

        if (!$colonne || !str_contains($colonne->COLUMN_TYPE, "'LIVRAISON'")) {
            return;
        }

        // Retirer LIVRAISON tronquerait les lignes qui la portent : on ne
        // revient en arrière que si aucune n'existe.
        if (DB::table('tva_commande')->where('type_affaire', 'LIVRAISON')->exists()) {
            return;
        }

        DB::statement($this->requeteModification($colonne, "ENUM('LOCATION','VENTE')"));
    }

    private function colonne(): ?object
    {
        $resultat = DB::select(
            "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'tva_commande'
                AND COLUMN_NAME  = 'type_affaire'"
        );

        return $resultat[0] ?? null;
    }

    private function requeteModification(object $colonne, string $type): string
    {
        $nullabilite = $colonne->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';

        $defaut = '';
        if ($colonne->COLUMN_DEFAULT !== null) {
            $defaut = " DEFAULT '" . addslashes($colonne->COLUMN_DEFAULT) . "'";
        } elseif ($colonne->IS_NULLABLE === 'YES') {
            $defaut = ' DEFAULT NULL';
        }

        return "ALTER TABLE `tva_commande` MODIFY `type_affaire` {$type} {$nullabilite}{$defaut}";
    }
};
