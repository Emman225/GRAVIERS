<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les champs nécessaires au stockage du résultat de la certification
 * d'une facture par la plateforme FNE de la DGI (Côte d'Ivoire).
 *
 * Référence : "PROCEDURE D'INTERFACAGE DES ENTREPRISES PAR API" (mai 2025)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            // Identifiant interne FNE (UUID renvoyé par la plateforme).
            $table->string('fne_invoice_id', 64)->nullable()->after('numero_fne');

            // Référence officielle FNE (numéro normalisé renvoyé par la DGI).
            // Format type : 9606123E25000000019
            $table->string('fne_reference', 60)->nullable()->after('fne_invoice_id');

            // URL de vérification (contient le QR code à apposer sur la facture).
            $table->string('fne_token', 255)->nullable()->after('fne_reference');

            // Stock restant de stickers FNE (alerte DGI quand bas).
            $table->integer('fne_balance_sticker')->nullable()->after('fne_token');
            $table->boolean('fne_warning')->default(false)->after('fne_balance_sticker');

            // Méta : template (B2B/B2C/B2G/B2F), mode de paiement, statut.
            $table->string('fne_template', 10)->nullable()->after('fne_warning');
            $table->string('fne_payment_method', 30)->nullable()->after('fne_template');

            // Statut de la certification :
            //  - pending     : pas encore tentée
            //  - certified   : certifiée avec succès
            //  - failed      : appel API en erreur (voir fne_error_message)
            //  - disabled    : module FNE désactivé localement (pas de credentials)
            $table->string('fne_status', 20)->default('pending')->after('fne_payment_method');

            $table->timestamp('fne_certified_at')->nullable()->after('fne_status');

            // Dernier message d'erreur renvoyé par la plateforme FNE.
            $table->text('fne_error_message')->nullable()->after('fne_certified_at');

            // Payloads complets pour audit & support DGI.
            $table->json('fne_request_payload')->nullable()->after('fne_error_message');
            $table->json('fne_response_payload')->nullable()->after('fne_request_payload');
        });
    }

    public function down(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            $table->dropColumn([
                'fne_invoice_id',
                'fne_reference',
                'fne_token',
                'fne_balance_sticker',
                'fne_warning',
                'fne_template',
                'fne_payment_method',
                'fne_status',
                'fne_certified_at',
                'fne_error_message',
                'fne_request_payload',
                'fne_response_payload',
            ]);
        });
    }
};
