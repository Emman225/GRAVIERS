<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Produit extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'produit';
    protected $fillable = [
        'reference',
        'nom',
        'abreviation',
        'unite',
        'description',
        'prix_moyen',
        'prix_reduction',
        'meilleur_note',
        'statut',
        'type_affaire',
    ];

    public static function lire($id)
    {
        $obj = Produit::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Produit();
    }

    public static function lireReference($reference)
    {
        $obj = Produit::where('reference', $reference)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Produit();
    }

    public static function liste($search = null, $limit = 100, $categories = [], $produits = [], $montants = [], $typeAffaire = null)
    {
        $url = Help::$URL_BASE_FICHIER;
        return self::appliquerPrixCatalogue(Produit::distinct()
            ->selectRaw("produit.*, concat('$url',image_produit.image) as image")
            ->orderBy('produit.nom', 'asc')
            ->join('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('produit.reference', $search);
                    $q->orWhere('produit.nom', 'LIKE', "%$search%");
                    $q->orWhere('produit.abreviation', 'LIKE', "%$search%");
                    $q->orWhere('produit.description', 'LIKE', "%$search%");
                });
            })
            ->when((count($categories) > 0), function ($query) use ($categories) {
                $query->leftJoin('categorie_produit', function ($join) use ($categories) {
                    $join->where(function ($q) use ($categories) {
                        $q->on('categorie_produit.produit_id', '=', 'produit.id');
                        $q->WhereIn('categorie_produit.categorie_id', $categories);
                    });
                });
            })
            ->when((count($produits) > 0), function ($query) use ($produits) {
                $query->orWhereIn('produit.id', $produits);
            })
            ->when((count($montants) > 0), function ($query) use ($montants) {
                $query->orWhereIn('produit.prix_moyen', $montants);
            })
            ->when($typeAffaire, function ($query) use ($typeAffaire) {
                $query->where('produit.type_affaire', $typeAffaire);
            })
            ->where('produit.statut', Help::$STATUT_ACTIF)
            ->limit($limit)
            ->get());
    }

    public static function listeSurCategorie($search = null, $limit = 100, $categories = [], $produits = [], $montants = [], $typeAffaire = null)
    {
        $url = Help::$URL_BASE_FICHIER;
        return self::appliquerPrixCatalogue(Produit::distinct()
            ->selectRaw("produit.*, concat('$url',image_produit.image) as image")
            ->orderBy('produit.nom', 'asc')
            ->join('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('produit.reference', $search);
                    $q->orWhere('produit.nom', 'LIKE', "%$search%");
                    $q->orWhere('produit.abreviation', 'LIKE', "%$search%");
                    $q->orWhere('produit.description', 'LIKE', "%$search%");
                });
            })
            ->when((count($categories) > 0), function ($query) use ($categories) {
                $query->join('categorie_produit', function ($join) use ($categories) {
                    $join->where(function ($q) use ($categories) {
                        $q->on('categorie_produit.produit_id', '=', 'produit.id');
                        $q->WhereIn('categorie_produit.categorie_id', $categories);
                    });
                });
            })
            ->when((count($produits) > 0), function ($query) use ($produits) {
                $query->orWhereIn('produit.id', $produits);
            })
            ->when((count($montants) > 0), function ($query) use ($montants) {
                $query->orWhereIn('produit.prix_moyen', $montants);
            })
            ->when($typeAffaire, function ($query) use ($typeAffaire) {
                $query->where('produit.type_affaire', $typeAffaire);
            })
            ->where('produit.statut', Help::$STATUT_ACTIF)
            ->limit($limit)
            ->get());
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Produit($arr);
        if ($obj->save()) return $obj;
        else return new Produit();
    }

    /**
     * Aligne « prix_moyen » sur le PRIX CATALOGUE réellement appliqué.
     *
     * Le site ne montre pas la colonne prix_moyen : il montre le prix du
     * fournisseur actif LE MOINS CHER, et c'est ce prix-là que le panier
     * facture (cf. Produit::prixPour() côté web). L'API, elle, renvoyait la
     * colonne brute — d'où un même article affiché 100 FCFA sur le site et
     * 2 000 FCFA dans l'application.
     *
     * L'écart ne restait pas à l'affichage : le mobile renvoie ensuite ce prix
     * au moment de commander, et la commande était donc facturée au mauvais
     * montant.
     *
     * Le prix NÉGOCIÉ d'un client n'est pas concerné : il est traité à part,
     * dans le champ « prix_personnalise » que les contrôleurs ajoutent déjà, et
     * il prime sur le prix catalogue — exactement comme sur le site.
     *
     * Une seule requête agrégée pour tout le lot : interroger la base produit
     * par produit sur un catalogue de plusieurs centaines d'articles serait
     * ruineux.
     */
    /**
     * Prix CATALOGUE de chaque produit : le prix du fournisseur actif le moins
     * cher, à défaut prix_moyen. C'est le prix que voit le client.
     *
     * Extrait de appliquerPrixCatalogue() pour être réutilisable au moment de
     * FACTURER. Le service de calcul des montants s'appuyait sur la colonne
     * prix_moyen brute : l'application affichait 100 FCFA (prix fournisseur) et
     * le serveur facturait 2 000 (prix_moyen). Corriger l'affichage sans
     * corriger la facturation ne réglait donc que la moitié du problème.
     *
     * Une seule requête agrégée pour tout le lot.
     *
     * @return array<int, float> [produit_id => prix]
     */
    public static function prixCatalogue(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return [];
        }

        $prixMini = StockProduit::whereIn('produit_id', $ids)
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->groupBy('produit_id')
            ->selectRaw('produit_id, MIN(prix) AS prix_mini')
            ->pluck('prix_mini', 'produit_id');

        $prix = [];

        foreach (Produit::whereIn('id', $ids)->get(['id', 'prix_moyen']) as $produit) {
            $prix[(int) $produit->id] = isset($prixMini[$produit->id])
                ? (float) $prixMini[$produit->id]
                : (float) ($produit->prix_moyen ?? 0);
        }

        return $prix;
    }

    public static function appliquerPrixCatalogue($produits)
    {
        if (empty($produits) || count($produits) === 0) {
            return $produits;
        }

        $ids = [];
        foreach ($produits as $p) {
            if (!empty($p->id)) {
                $ids[] = $p->id;
            }
        }

        if (empty($ids)) {
            return $produits;
        }

        $prixMini = StockProduit::whereIn('produit_id', array_unique($ids))
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->groupBy('produit_id')
            ->selectRaw('produit_id, MIN(prix) AS prix_mini')
            ->pluck('prix_mini', 'produit_id');

        foreach ($produits as $p) {
            // Repli implicite sur prix_moyen quand AUCUN fournisseur n'a fixé de
            // prix : même règle que le site, qui ne laisse jamais un produit
            // sans prix affichable.
            if (isset($prixMini[$p->id])) {
                $p->prix_moyen = (float) $prixMini[$p->id];
            }
        }

        return $produits;
    }

    public static function supprimer($id)
    {
        $obj = Produit::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
