<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UN REÇU DOIT NOMMER CE QU'ON PAIE.
 *
 * Le reçu RC-2026-002 du 25/08/2026 — 67 840 F en espèces — portait
 * « N° Commande : - ». Il s'agissait d'une LOCATION, et rien sur le document ne
 * la désignait : seule une note en bas de page, en petits caractères, mentionnait
 * « location 382108 ».
 *
 * Pire que le libellé : les montants. Le reçu ne résolvait que les COMMANDES ;
 * pour tout le reste il repliait ses totaux sur la somme versée. Il annonçait
 * donc « Total 67 840, payé 67 840, reste 0 » sur une location qui n'en devait
 * que 55 920 — un document qui certifiait un solde inexact.
 *
 * Les trois modèles — Commande, Location, DemandeLivraison — exposent
 * montantAPayer(), montantPayeComptant() et montantRestantDu(). Le reçu les
 * interroge désormais de la même façon, quel que soit le service.
 */
class RecuNommeLOperationTest extends TestCase
{
    private function controleur(): string
    {
        return file_get_contents(app_path('Http/Controllers/CommandeComptantController.php'));
    }

    /** Les trois natures d'opération doivent être résolues. */
    public function test_les_trois_services_sont_resolus(): void
    {
        $code = $this->controleur();

        $this->assertStringContainsString("\$paiement->service === 'COMMANDE'", $code);
        $this->assertStringContainsString('$paiement->service === Help::$LOCATION', $code,
            "Une location réglée en agence doit être reconnue.");
        $this->assertStringContainsString('$paiement->service === Help::$LIVRAISON', $code,
            "Un transport réglé en agence doit être reconnu.");
    }

    /** Le libellé suit la nature de l'opération. */
    public function test_le_libelle_suit_la_nature_de_l_operation(): void
    {
        $code = $this->controleur();

        foreach (['N° Commande', 'N° Location', 'N° Demande de livraison'] as $libelle) {
            $this->assertStringContainsString("'{$libelle}'", $code,
                "Le reçu doit pouvoir s'intituler « {$libelle} ».");
        }

        $vue = file_get_contents(resource_path('views/admin/comptant/_recu_body.blade.php'));

        $this->assertStringContainsString('$libelleOperation', $vue,
            "Le reçu ne doit plus coder « N° Commande » en dur.");
    }

    /**
     * LA TRANCHE SE COMPTE SUR LE MÊME SERVICE.
     *
     * Le comptage forçait « service = COMMANDE ». Une commande, une location et
     * une demande peuvent porter le MÊME identifiant : sans ce filtre, le reçu
     * d'une location aurait compté les tranches d'une commande homonyme.
     */
    public function test_la_tranche_se_compte_sur_le_meme_service(): void
    {
        $this->assertStringContainsString(
            "\$qq->where('service', \$paiement->service)->where('service_id', \$cmd->id);",
            $this->controleur(),
            'Le comptage des tranches doit rester dans le service du paiement.'
        );
    }
}
