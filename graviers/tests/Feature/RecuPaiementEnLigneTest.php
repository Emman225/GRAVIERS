<?php

namespace Tests\Feature;

use App\Mail\DocumentPdfMail;
use App\Models\Client;
use App\Models\LignePaiement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\RecuPaiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * LE REÇU DE PAIEMENT EN LIGNE : LE MODÈLE DU GUICHET, ENVOYÉ UNE FOIS.
 *
 * Points 10 et 11 du 07/09/2026. Après un paiement en ligne, le client
 * recevait une « facture » à 0 (rien n'est enlevé au moment du paiement) et
 * jamais de reçu. Son espace « Mes paiements » montrait un document d'un
 * autre dessin, désignation = code interne du paiement.
 */
class RecuPaiementEnLigneTest extends TestCase
{
    use DatabaseTransactions;

    /** Un règlement de commande dont le client a un courriel. */
    private function unPaiementDeCommande(): Paiement
    {
        $p = Paiement::where('service', 'COMMANDE')
            ->whereNotNull('service_id')
            ->whereHas('client.user', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''))
            ->orderBy('id')
            ->first();

        if (!$p) {
            $this->markTestSkipped('Aucun règlement de commande avec un client joignable.');
        }

        return $p;
    }

    public function test_les_donnees_du_recu_nomment_la_commande(): void
    {
        $p = $this->unPaiementDeCommande();

        $donnees = RecuPaiement::donnees($p);

        $this->assertSame('N° Commande', $donnees['libelleOperation']);
        $this->assertNotNull($donnees['commande']);
        $this->assertNotEmpty($donnees['commande']->numero);
        $this->assertArrayHasKey('enLigne', $donnees);
    }

    public function test_le_recu_part_une_seule_fois_quel_que_soit_le_chemin(): void
    {
        Mail::fake();
        $p = $this->unPaiementDeCommande();
        $p->recu_envoye_le = null;
        $p->save();

        $this->assertTrue(RecuPaiement::envoyerParCourriel($p, true), 'Premier chemin : le reçu doit partir.');
        // Deux autres chemins (passerelle, vérification planifiée) confirment le
        // même règlement : ils doivent se taire.
        $this->assertFalse(RecuPaiement::envoyerParCourriel($p, true));
        $this->assertFalse(RecuPaiement::envoyerParCourriel($p, true));

        Mail::assertSent(DocumentPdfMail::class, 1);
        Mail::assertSent(DocumentPdfMail::class, function (DocumentPdfMail $m) {
            return $m->typeDocument === 'Reçu de paiement'
                && str_starts_with($m->nomFichier, 'Recu_')
                && str_starts_with($m->pdfContent, '%PDF');
        });

        $p->refresh();
        $this->assertNotNull($p->recu_envoye_le);
        $this->assertNotEmpty($p->numero_recu, 'Un reçu envoyé porte un numéro.');
    }

    public function test_un_paiement_en_ligne_recoit_un_numero_RL(): void
    {
        $p = $this->unPaiementDeCommande();
        $p->agence_id = null;
        $p->caissier_id = null;
        $p->numero_recu = null;
        $p->save();

        $numero = RecuPaiement::attribuerNumeroSiAbsent($p);

        $this->assertMatchesRegularExpression('/^RL-\d{4}-\d{3}$/', $numero);
        $this->assertSame($numero, $p->fresh()->numero_recu);
        // Une seconde demande ne renumérote pas.
        $this->assertSame($numero, RecuPaiement::attribuerNumeroSiAbsent($p->fresh()));
    }

    public function test_le_client_voit_son_recu_au_modele_du_guichet_et_pas_celui_d_un_autre(): void
    {
        $p = $this->unPaiementDeCommande();
        $ligne = LignePaiement::where('paiement_id', $p->id)->first();
        if (!$ligne) {
            $this->markTestSkipped('Règlement sans ligne de paiement.');
        }

        $proprietaire = $p->client->user;
        Auth::guard('web')->login($proprietaire);

        $reponse = $this->get(route('paye.facture', ['reference' => $ligne->id, 'action' => 'voir']));
        $reponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $reponse->headers->get('Content-Type'));

        // Un AUTRE client : refusé.
        $autre = Client::where('id', '<>', $p->client_id)
            ->whereHas('user', fn ($q) => $q->where('type_user_id', \Help::$USER_CLIENT))
            ->first();
        if ($autre && $autre->user) {
            Auth::guard('web')->logout();
            Auth::guard('web')->login($autre->user);
            $this->get(route('paye.facture', ['reference' => $ligne->id, 'action' => 'voir']))->assertStatus(403);
        }
    }
}
