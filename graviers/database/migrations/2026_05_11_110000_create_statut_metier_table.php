<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('statut_metier')) {
            Schema::create('statut_metier', function (Blueprint $table) {
                $table->id();
                $table->string('domaine', 40)->index();
                $table->string('libelle', 80);
                $table->string('badge_class', 60)->default('bg-light text-dark');
                $table->string('description', 255)->nullable();
                $table->unsignedInteger('ordre')->default(0);
                $table->boolean('statut')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['domaine', 'libelle']);
            });
        }

        $domaines = [
            'creance_terme' => [
                ['À échoir',          'bg-info text-white',      'Facture émise, échéance non atteinte'],
                ['Échue partielle',   'bg-warning text-dark',    'Échéance dépassée, paiement partiel reçu'],
                ['Échue impayée',     'bg-danger text-white',    'Échéance dépassée, aucun paiement'],
                ['Soldée',            'bg-success text-white',   'Facture entièrement payée'],
                ['Litige',            'bg-dark text-white',      'Facture contestée par le client'],
                ['Annulée',           'bg-secondary text-white', 'Facture annulée'],
            ],
            'comptant' => [
                ['En attente paiement',   'bg-info text-white',      'Commande créée, paiement non encore effectué'],
                ['Payée',                 'bg-success text-white',   'Client a payé en agence'],
                ['Partiellement payée',   'bg-warning text-dark',    'Acompte versé en agence'],
                ['Livrée',                'bg-primary text-white',   'Commande payée et livrée'],
                ['En retard',             'bg-danger text-white',    'Délai dépassé sans paiement'],
                ['Annulée',               'bg-secondary text-white', 'Commande annulée (non paiement ou autre)'],
            ],
            'dette_fournisseur' => [
                ['À payer',               'bg-warning text-dark',    'Enlèvement effectué, paiement non encore fait'],
                ['Payée',                 'bg-success text-white',   'Dette intégralement réglée'],
                ['Partiellement payée',   'bg-info text-white',      'Acompte versé'],
                ['En litige',             'bg-dark text-white',      'Désaccord sur facturation'],
                ['Échue impayée',         'bg-danger text-white',    'Délai de paiement dépassé'],
            ],
            'dette_livreur' => [
                ['Livraison effectuée',   'bg-info text-white',      'Livraison réussie, en attente de validation'],
                ['Validée à payer',       'bg-warning text-dark',    'Livraison validée, à payer au prochain cycle'],
                ['Payée',                 'bg-success text-white',   'Frais de livraison versés au livreur'],
                ['En contestation',       'bg-dark text-white',      'Litige avec le livreur ou le client'],
                ['Annulée',               'bg-secondary text-white', 'Livraison non effectuée'],
            ],
            'commission_apporteur' => [
                ['En attente paiement client', 'bg-info text-white',    'Commande créée mais client n\'a pas encore payé'],
                ['Due',                       'bg-warning text-dark',   'Client a payé, commission devient exigible'],
                ['Partiellement due',         'bg-info text-white',     'Client a payé partiellement (commission proportionnelle)'],
                ['Payée',                     'bg-success text-white',  'Commission versée à l\'apporteur'],
                ['Annulée',                   'bg-secondary text-white','Commande annulée, commission supprimée'],
            ],
        ];

        foreach ($domaines as $domaine => $rows) {
            $ordre = 1;
            foreach ($rows as [$libelle, $badge, $description]) {
                $exists = DB::table('statut_metier')
                    ->where('domaine', $domaine)
                    ->where('libelle', $libelle)
                    ->exists();
                if (!$exists) {
                    DB::table('statut_metier')->insert([
                        'domaine'     => $domaine,
                        'libelle'     => $libelle,
                        'badge_class' => $badge,
                        'description' => $description,
                        'ordre'       => $ordre++,
                        'statut'      => 1,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('statut_metier');
    }
};
