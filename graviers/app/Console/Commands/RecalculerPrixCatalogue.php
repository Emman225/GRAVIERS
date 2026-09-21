<?php

namespace App\Console\Commands;

use App\Models\PourcentageDalakoun;
use App\Models\Produit;
use Illuminate\Console\Command;

/**
 * Aligne le prix affiché de chaque produit sur le calcul officiel :
 * prix d'achat le plus élevé × (1 + pourcentage DALAKOUN).
 *
 * `produit.prix_moyen` porte le prix montré au catalogue. Il était saisi à la
 * main sur l'écran d'ajout de produit, sans rapport avec le prix d'achat — d'où
 * une bétonnière annoncée à 20 000 et facturée 100. Le prix se calcule
 * désormais ; cette commande met la colonne d'accord avec le calcul, en une
 * fois, pour les produits déjà en ligne.
 *
 * SIMULATION PAR DÉFAUT. Rien n'est écrit sans --apply : on veut voir les
 * nouveaux prix avant de les appliquer à tout le catalogue.
 */
class RecalculerPrixCatalogue extends Command
{
    protected $signature = 'produit:recalculer-prix
                            {--apply : Écrit réellement les nouveaux prix}
                            {--tout : Affiche tous les produits, y compris ceux qui ne changent pas}';

    protected $description = "Recalcule le prix catalogue de chaque produit à partir du prix d'achat et du pourcentage DALAKOUN";

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $taux = PourcentageDalakoun::enVigueur();

        if (!$taux) {
            $this->error('Aucun pourcentage DALAKOUN n\'est en vigueur.');
            $this->line('Saisissez-en un dans Produits → Pourcentage DALAKOUN, faites-le valider par un');
            $this->line('second administrateur, puis relancez cette commande.');

            return self::FAILURE;
        }

        $this->info('Pourcentage en vigueur : ' . $this->nombre($taux->taux) . ' %');
        $this->newLine();

        $this->signalerLesPrixSuspects();

        $lignes = [];
        $sansPrixAchat = 0;

        foreach (Produit::orderBy('nom')->get() as $produit) {
            $prixAchat = Produit::prixAchatDe($produit->id);

            if ($prixAchat === null) {
                // Aucun fournisseur ne l'a tarifé : on ne peut pas calculer une
                // marge sur un coût inconnu, et on ne touche à rien.
                $sansPrixAchat++;
                continue;
            }

            $tauxProduit = $produit->tauxDalakoun();
            $nouveau     = round($prixAchat * (1 + $tauxProduit / 100));
            $ancien      = round((float) $produit->prix_moyen);

            if (!$this->option('tout') && $nouveau === $ancien) {
                continue;
            }

            $lignes[] = [
                'id'        => $produit->id,
                'nom'       => mb_substr($produit->nom, 0, 34),
                'achat'     => $prixAchat,
                'taux'      => $tauxProduit,
                'derogation'=> $produit->pourcentage_dalakoun !== null ? 'oui' : '',
                'ancien'    => $ancien,
                'nouveau'   => $nouveau,
            ];
        }

        if (empty($lignes)) {
            $this->info('Aucun prix à recalculer : le catalogue est déjà à jour.');

            if ($sansPrixAchat > 0) {
                $this->warn($sansPrixAchat . ' produit(s) sans prix d\'achat, laissés inchangés.');
            }

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Produit', 'Prix achat', 'Taux', 'Dérog.', 'Ancien prix', 'Nouveau prix'],
            array_map(fn ($l) => [
                $l['id'],
                $l['nom'],
                $this->nombre($l['achat']),
                $this->nombre($l['taux']) . ' %',
                $l['derogation'],
                $this->nombre($l['ancien']),
                $this->nombre($l['nouveau']),
            ], $lignes)
        );

        $this->line(count($lignes) . ' produit(s) concerné(s).');

        if ($sansPrixAchat > 0) {
            $this->warn($sansPrixAchat . ' produit(s) sans prix d\'achat : ils gardent leur prix actuel.');
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');

            return self::SUCCESS;
        }

        foreach ($lignes as $l) {
            Produit::where('id', $l['id'])->update(['prix_moyen' => $l['nouveau']]);
        }

        $this->newLine();
        $this->info(count($lignes) . ' prix mis à jour.');

        return self::SUCCESS;
    }

    /**
     * Avertit AVANT le tableau : un prix d'achat aberrant produit désormais un
     * prix de vente aberrant, et se noie dans une liste de vingt-six lignes.
     */
    private function signalerLesPrixSuspects(): void
    {
        $suspects = Produit::prixAchatSuspects();

        if (empty($suspects)) {
            return;
        }

        $this->warn('PRIX D\'ACHAT À VÉRIFIER — ils s\'écartent fortement de leur famille :');

        $this->table(
            ['#', 'Produit', 'Prix achat', 'Médiane', 'Rapport', 'Fournisseur', 'Corriger sur'],
            array_map(fn ($s) => [
                $s['id'],
                mb_substr($s['nom'], 0, 28),
                $this->nombre($s['prix']),
                $this->nombre($s['mediane']),
                // Un rapport inférieur à 1 s'énonce mieux à l'envers :
                // « ÷ 205 » se lit, « × 0 » ne dit rien.
                $s['rapport'] >= 1
                    ? '× ' . $this->nombre(round($s['rapport'], 1))
                    : '÷ ' . $this->nombre(round(1 / max($s['rapport'], 0.0000001), 1)),
                mb_substr($s['fournisseur'], 0, 22),
                // La fiche produit liste les prix d'achat de chaque fournisseur
                // et les rend corrigeables. L'espace fournisseur, lui, n'est
                // accessible qu'au fournisseur lui-même, connecté.
                '/products-edit/' . $s['id'],
            ], $suspects)
        );

        $this->line('Un prix peut légitimement sortir du lot : ceci est un signal, pas un verdict.');
        $this->line('Corrigez le prix d\'achat sur la fiche du produit, ligne du fournisseur indiqué.');
        $this->newLine();
    }

    private function nombre($valeur): string
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, ',', ' '), '0'), ',');
    }
}
