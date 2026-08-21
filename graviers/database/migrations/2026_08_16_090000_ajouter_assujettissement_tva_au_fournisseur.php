<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le fournisseur est-il assujetti à la TVA ?
     *
     * Le client paie le produit, la TVA et le transport. Les trois n'ont pas le
     * même destinataire : le produit revient au fournisseur, la TVA à l'État et
     * le transport à l'entreprise. Le règlement du fournisseur doit donc porter
     * sur le SEUL coût du produit — le système lui versait jusqu'ici 18 % de
     * plus, c'est-à-dire la TVA que l'entreprise doit garder pour la déclarer.
     *
     * Un fournisseur déclaré, lui, facture bel et bien la TVA : la lui refuser
     * créerait un écart avec sa facture, et priverait l'entreprise d'une TVA
     * déductible. D'où cet indicateur.
     *
     * Par défaut À FAUX : le comportement correspond à la règle voulue pour
     * l'ensemble des fournisseurs actuels, et rien ne change sans une action
     * explicite.
     */
    public function up(): void
    {
        if (Schema::hasColumn('fournisseur', 'assujetti_tva')) {
            return;
        }

        Schema::table('fournisseur', function (Blueprint $table) {
            $table->boolean('assujetti_tva')->default(false)->after('registre_commerce');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('fournisseur', 'assujetti_tva')) {
            return;
        }

        Schema::table('fournisseur', function (Blueprint $table) {
            $table->dropColumn('assujetti_tva');
        });
    }
};
