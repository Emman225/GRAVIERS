<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Enlevement;
use App\Models\Produit;
use App\Models\StockProduit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Un prix d'achat hors de proportion est signalé avant tout recalcul.
 *
 * Depuis que le prix de vente se calcule à partir du prix d'achat, une faute de
 * frappe sur ce dernier ne fausse plus seulement ce qu'on verse au fournisseur :
 * elle fait le prix montré au client. Une barre de fer saisie 50 000 au lieu de
 * 5 000 s'est retrouvée affichée 55 000 en boutique, entre des voisines à 2 200
 * et 7 480 — et personne ne l'a vue dans un tableau de vingt-six lignes.
 *
 * Les seuils sont réglés sur les familles réelles du catalogue, et ce test les
 * y confronte : il doit attraper les deux erreurs constatées sans signaler les
 * produits légitimement plus chers ou moins chers que leurs voisins.
 */
class CoherencePrixAchatTest extends TestCase
{
    use DatabaseTransactions;

    /** Une famille de produits isolée, avec les prix d'achat voulus. */
    private function uneFamille(array $prix): array
    {
        $modele = Produit::first();
        $bon    = Enlevement::whereNotNull('fournisseur_id')->first();

        if (!$modele || !$bon) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        $categorie = Categorie::create([
            'nom'         => 'Famille ' . substr((string) uniqid(), -8),
            'description' => 'Créée pour la recette.',
            'parent_id'   => 0,
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        $produits = [];

        foreach ($prix as $nom => $montant) {
            $produit = Produit::create([
                'nom'              => $nom . ' ' . uniqid(),
                'description'      => 'Produit créé pour la recette.',
                'unite_produit_id' => $modele->unite_produit_id,
                'type_affaire'     => 'VENTE',
                'prix_moyen'       => $montant,
                'statut'           => \Help::$STATUT_ACTIF,
            ]);

            $produit->categories()->attach($categorie->id);

            StockProduit::create([
                'fournisseur_id' => $bon->fournisseur_id,
                'produit_id'     => $produit->id,
                'qte'            => 10,
                'prix'           => $montant,
                'seuil_alert'    => 0,
                'statut'         => \Help::$STATUT_ACTIF,
            ]);

            $produits[$nom] = $produit;
        }

        return $produits;
    }

    /** Les noms des produits signalés parmi ceux de la famille. */
    private function signales(array $famille): array
    {
        $ids = collect($famille)->pluck('id')->all();

        return collect(Produit::prixAchatSuspects())
            ->filter(fn ($s) => in_array($s['id'], $ids, true))
            ->pluck('nom')
            ->map(fn ($nom) => explode(' ', $nom)[0])
            ->values()
            ->all();
    }

    public function test_la_barre_de_fer_a_cinquante_mille_est_attrapee(): void
    {
        // La famille réelle : 6, 8, 10 et 12 mm, plus le treillis soudé.
        // La 10 mm porte l'erreur — 50 000 au lieu de 5 000.
        $famille = $this->uneFamille([
            'six'      => 2000,
            'huit'     => 3200,
            'dix'      => 50000,
            'douze'    => 6800,
            'treillis' => 12000,
        ]);

        $signales = $this->signales($famille);

        $this->assertContains('dix', $signales, 'La barre à 50 000 doit être signalée.');
    }

    public function test_le_sable_de_mer_a_vingt_cinq_mille_est_attrape(): void
    {
        // Médiane 9 000, le sable de mer à 25 000 ressort à 2,8 : il passait
        // sous un seuil de 3, d'où le réglage à 2,5.
        $famille = $this->uneFamille([
            'fin'  => 8000,
            'gros' => 9000,
            'mer'  => 25000,
        ]);

        $this->assertContains('mer', $this->signales($famille));
    }

    public function test_un_produit_legitimement_plus_cher_n_est_pas_signale(): void
    {
        // Le treillis soudé coûte naturellement plus qu'une barre : 12 000 pour
        // une médiane de 6 800, soit 1,8. Il ne doit pas alerter.
        $famille = $this->uneFamille([
            'six'      => 2000,
            'huit'     => 3200,
            'douze'    => 6800,
            'treillis' => 12000,
            'lourde'   => 9000,
        ]);

        $this->assertNotContains('treillis', $this->signales($famille));
    }

    public function test_un_produit_legitimement_moins_cher_n_est_pas_signale(): void
    {
        // Une barre de 6 mm coûte trois fois moins qu'une de 12 mm : c'est la
        // physique, pas une erreur. Un seuil symétrique l'aurait signalée.
        $famille = $this->uneFamille([
            'six'      => 2000,
            'huit'     => 3200,
            'douze'    => 6800,
            'treillis' => 12000,
            'lourde'   => 9000,
        ]);

        $this->assertNotContains('six', $this->signales($famille));
    }

    public function test_une_famille_trop_petite_ne_dit_rien(): void
    {
        // Avec deux produits, la médiane est le produit lui-même ou son unique
        // voisin : elle ne prouve rien.
        $famille = $this->uneFamille([
            'un'   => 1000,
            'deux' => 900000,
        ]);

        $this->assertSame([], $this->signales($famille));
    }

    public function test_une_famille_homogene_ne_signale_rien(): void
    {
        // Vos graviers : 11 000 à 13 500. Aucun ne doit alerter.
        $famille = $this->uneFamille([
            'toutvenant' => 11000,
            'zerocinq'   => 12000,
            'cinqquinze' => 13000,
            'quinze'     => 13500,
        ]);

        $this->assertSame([], $this->signales($famille));
    }

    public function test_le_signal_dit_chez_quel_fournisseur_corriger(): void
    {
        // Signaler un prix faux sans dire où le corriger, c'est laisser
        // chercher : un prix d'achat appartient à un couple produit x
        // fournisseur, et la fiche du produit ne l'ecrit pas.
        $famille = $this->uneFamille([
            'fin'  => 8000,
            'gros' => 9000,
            'mer'  => 25000,
        ]);

        $suspect = collect(Produit::prixAchatSuspects())
            ->firstWhere('id', $famille['mer']->id);

        $this->assertNotNull($suspect, 'Le sable a 25 000 doit etre signale.');

        $attendu = StockProduit::where('produit_id', $famille['mer']->id)
            ->orderByDesc('prix')
            ->first();

        $this->assertSame((int) $attendu->fournisseur_id, (int) $suspect['fournisseur_id'],
            'Le signal doit designer le fournisseur qui porte le prix retenu.');

        $this->assertNotSame('', trim((string) $suspect['fournisseur']),
            'Le nom du fournisseur doit accompagner le signal.');
    }

    public function test_le_fournisseur_designe_est_celui_du_prix_retenu(): void
    {
        // Le prix de vente suit le fournisseur LE PLUS CHER : c'est donc chez
        // lui qu'il faut corriger, pas chez le premier venu.
        $famille = $this->uneFamille([
            'fin'  => 8000,
            'gros' => 9000,
            'mer'  => 25000,
        ]);

        $autre = \App\Models\Fournisseur::where(
            'id', '!=', StockProduit::where('produit_id', $famille['mer']->id)->value('fournisseur_id')
        )->first();

        if (!$autre) {
            $this->markTestSkipped('Un seul fournisseur en base.');
        }

        // Un second fournisseur, moins cher, sur le meme produit.
        StockProduit::create([
            'fournisseur_id' => $autre->id,
            'produit_id'     => $famille['mer']->id,
            'qte'            => 5,
            'prix'           => 9500,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $suspect = collect(Produit::prixAchatSuspects())
            ->firstWhere('id', $famille['mer']->id);

        $this->assertNotSame((int) $autre->id, (int) $suspect['fournisseur_id'],
            'Le fournisseur le moins cher ne dicte pas le prix : la correction se fait ailleurs.');
    }
}
