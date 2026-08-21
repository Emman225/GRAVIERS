<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ramène tous les numéros de commande au format à 6 chiffres.
 *
 * Deux générateurs coexistaient : une commande passée directement depuis le
 * panier recevait 6 chiffres, une commande née d'une demande de devis recevait
 * AAMMJJ suivi de 6 chiffres. Le format dépendait donc du chemin emprunté par le
 * client, pour une même prestation.
 *
 * La commande reprend le numéro de son devis : les deux sont renumérotés
 * ensemble, sans quoi ils divergeraient. paiement.code reçoit lui aussi ce
 * numéro dans un des parcours d'encaissement (ClientController::5317) ; il suit
 * quand il correspond exactement à l'ancien numéro.
 *
 * Chaque changement est consigné dans `renumerotation_commande`. C'est
 * volontaire : un client qui appellera avec son ancien numéro doit pouvoir être
 * retrouvé, et une renumérotation sans trace serait irréversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('renumerotation_commande')) {
            Schema::create('renumerotation_commande', function (Blueprint $table) {
                $table->id();
                $table->string('ancien_numero', 40)->index();
                $table->string('nouveau_numero', 20)->index();
                $table->unsignedBigInteger('commande_id')->nullable();
                $table->boolean('devis_suivi')->default(false);
                $table->boolean('paiement_suivi')->default(false);
                $table->timestamps();
            });
        }

        // Sont concernées les commandes dont le numéro n'est pas exactement six
        // chiffres. Celles qui le sont déjà ne bougent pas : les renuméroter
        // ferait perdre leur référence sans rien uniformiser.
        $aTraiter = DB::table('commande')
            ->whereRaw("numero NOT REGEXP '^[0-9]{6}$'")
            ->orderBy('id')
            ->get(['id', 'numero', 'devis_id']);

        if ($aTraiter->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($aTraiter) {
            foreach ($aTraiter as $commande) {
                $ancien = (string) $commande->numero;
                $nouveau = $this->numeroLibre();

                DB::table('commande')->where('id', $commande->id)
                    ->update(['numero' => $nouveau]);

                // Le devis ne suit que si son numéro est bien celui de la commande.
                $devisSuivi = DB::table('devis')
                    ->where('numero', $ancien)
                    ->update(['numero' => $nouveau]);

                $paiementSuivi = DB::table('paiement')
                    ->where('code', $ancien)
                    ->update(['code' => $nouveau]);

                DB::table('renumerotation_commande')->insert([
                    'ancien_numero'  => $ancien,
                    'nouveau_numero' => $nouveau,
                    'commande_id'    => $commande->id,
                    'devis_suivi'    => $devisSuivi > 0,
                    'paiement_suivi' => $paiementSuivi > 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        });
    }

    /**
     * Un numéro à 6 chiffres libre dans devis ET dans commande.
     *
     * La génération est refaite ici plutôt qu'appelée depuis Help : une migration
     * de données doit rester fidèle à ce qu'elle a produit le jour où elle a été
     * jouée, même si le code applicatif évolue ensuite.
     */
    private function numeroLibre(): string
    {
        for ($tentative = 0; $tentative < 200; $tentative++) {
            $candidat = (string) random_int(100000, 999999);

            $pris = DB::table('commande')->where('numero', $candidat)->exists()
                || DB::table('devis')->where('numero', $candidat)->exists();

            if (!$pris) {
                return $candidat;
            }
        }

        throw new RuntimeException(
            "Impossible de trouver un numéro à 6 chiffres libre après 200 tirages. "
            . "L'espace de numérotation est saturé : élargir la largeur avant de rejouer."
        );
    }

    /**
     * Volontairement sans effet.
     *
     * Les anciens numéros sont conservés dans `renumerotation_commande`, mais les
     * restaurer automatiquement rouvrirait les collisions que cette migration
     * vient de résoudre. Le retour en arrière se fait à la main, à partir de cette
     * table, en connaissance de cause.
     */
    public function down(): void
    {
    }
};
