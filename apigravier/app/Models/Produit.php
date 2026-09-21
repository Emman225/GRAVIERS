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

    /**
     * L'image à montrer pour un produit, en SOUS-REQUÊTE.
     *
     * POURQUOI PAS UNE JOINTURE
     *
     * Le catalogue joignait `image_produit` en jointure INTERNE, en exigeant
     * une image marquée « par défaut » ET active. Conséquence : un produit dont
     * l'image existe mais n'est pas marquée par défaut DISPARAISSAIT
     * complètement de l'application — du catalogue, de la recherche et de sa
     * catégorie — alors que le site continuait de l'afficher, lui qui ne joint
     * jamais cette table. Le vendeur voyait son article en ligne, le client ne
     * pouvait pas l'acheter.
     *
     * Une jointure EXTERNE ne suffisait pas : un produit portant plusieurs
     * images serait revenu en autant d'exemplaires, et `distinct()` ne les
     * aurait pas fondus puisque l'image diffère d'une ligne à l'autre.
     *
     * La sous-requête, elle, ne retire aucun produit et n'en duplique aucun :
     * elle rend l'image par défaut quand il y en a une, sinon la première image
     * active, sinon NULL — au client d'afficher alors son visuel de repli.
     */
    private static function sousRequeteImage(): string
    {
        $url = Help::$URL_BASE_FICHIER;
        $actif = (int) Help::$STATUT_ACTIF;

        return "(SELECT CONCAT('$url', ip.image) FROM image_produit ip"
            . " WHERE ip.produit_id = produit.id"
            . " AND ip.statut = $actif"
            . " AND ip.deleted_at IS NULL"
            . " ORDER BY ip.defaut DESC, ip.id ASC LIMIT 1)";
    }

    public static function liste($search = null, $limit = 100, $categories = [], $produits = [], $montants = [], $typeAffaire = null)
    {
        return self::appliquerPrixCatalogue(Produit::distinct()
            ->selectRaw('produit.*, ' . self::sousRequeteImage() . ' as image')
            ->orderBy('produit.nom', 'asc')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('produit.reference', $search);
                    $q->orWhere('produit.nom', 'LIKE', "%$search%");
                    $q->orWhere('produit.abreviation', 'LIKE', "%$search%");
                    $q->orWhere('produit.description', 'LIKE', "%$search%");
                });
            })
            // LA CATÉGORIE RESTREINT VRAIMENT.
            //
            // Le filtre passait par un leftJoin dont la condition vivait DANS la
            // jointure : une jointure externe ne retire aucune ligne, elle se
            // contente de ne rien rattacher. Le catalogue revenait donc entier —
            // cocher « Gravier » ou « Sable » rendait les 25 produits dans les
            // deux cas, et le filtre n'a jamais rien filtré.
            //
            // whereExists restreint sans dupliquer les lignes, ce qu'une
            // jointure ferait pour un produit rangé dans plusieurs catégories.
            ->when((count($categories) > 0), function ($query) use ($categories) {
                $query->whereExists(function ($q) use ($categories) {
                    $q->selectRaw('1')
                        ->from('categorie_produit')
                        ->whereColumn('categorie_produit.produit_id', 'produit.id')
                        ->whereIn('categorie_produit.categorie_id', $categories);
                });
            })
            // ET, pas OU. `orWhereIn` ÉLARGISSAIT le résultat : cocher une
            // catégorie puis un produit rendait ce produit même s'il n'était pas
            // dans la catégorie. Un filtre restreint, il n'ajoute pas.
            ->when((count($produits) > 0), function ($query) use ($produits) {
                $query->whereIn('produit.id', $produits);
            })
            ->when($typeAffaire, function ($query) use ($typeAffaire) {
                $query->where('produit.type_affaire', $typeAffaire);
            })
            ->where('produit.statut', Help::$STATUT_ACTIF)
            ->limit($limit)
            ->get(), $montants);
    }

    public static function listeSurCategorie($search = null, $limit = 100, $categories = [], $produits = [], $montants = [], $typeAffaire = null)
    {
        // Même règle que pour le catalogue : voir sousRequeteImage().
        return self::appliquerPrixCatalogue(Produit::distinct()
            ->selectRaw('produit.*, ' . self::sousRequeteImage() . ' as image')
            ->orderBy('produit.nom', 'asc')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('produit.reference', $search);
                    $q->orWhere('produit.nom', 'LIKE', "%$search%");
                    $q->orWhere('produit.abreviation', 'LIKE', "%$search%");
                    $q->orWhere('produit.description', 'LIKE', "%$search%");
                });
            })
            // LA CATÉGORIE RESTREINT VRAIMENT.
            //
            // Le filtre passait par un leftJoin dont la condition vivait DANS la
            // jointure : une jointure externe ne retire aucune ligne, elle se
            // contente de ne rien rattacher. Le catalogue revenait donc entier —
            // cocher « Gravier » ou « Sable » rendait les 25 produits dans les
            // deux cas, et le filtre n'a jamais rien filtré.
            //
            // whereExists restreint sans dupliquer les lignes, ce qu'une
            // jointure ferait pour un produit rangé dans plusieurs catégories.
            ->when((count($categories) > 0), function ($query) use ($categories) {
                $query->whereExists(function ($q) use ($categories) {
                    $q->selectRaw('1')
                        ->from('categorie_produit')
                        ->whereColumn('categorie_produit.produit_id', 'produit.id')
                        ->whereIn('categorie_produit.categorie_id', $categories);
                });
            })
            // ET, pas OU. `orWhereIn` ÉLARGISSAIT le résultat : cocher une
            // catégorie puis un produit rendait ce produit même s'il n'était pas
            // dans la catégorie. Un filtre restreint, il n'ajoute pas.
            ->when((count($produits) > 0), function ($query) use ($produits) {
                $query->whereIn('produit.id', $produits);
            })
            ->when($typeAffaire, function ($query) use ($typeAffaire) {
                $query->where('produit.type_affaire', $typeAffaire);
            })
            ->where('produit.statut', Help::$STATUT_ACTIF)
            ->limit($limit)
            ->get(), $montants);
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
     * Le site ne montre pas la colonne prix_moyen : il montre le prix d'achat
     * LE PLUS ÉLEVÉ majoré du pourcentage DALAKOUN, et c'est ce prix-là que le
     * panier facture (cf. Produit::prixPour() côté web). L'API renvoyait la
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
     * Prix CATALOGUE de chaque produit : le prix d'achat le plus élevé parmi
     * les fournisseurs actifs, majoré du pourcentage DALAKOUN ; à défaut
     * prix_moyen. C'est le prix que voit le client, et celui qui sera facturé.
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

        // LE PRIX DE VENTE = PRIX D'ACHAT LE PLUS ÉLEVÉ, MAJORÉ DU POURCENTAGE.
        //
        // L'API retenait le prix du fournisseur LE MOINS CHER, et le vendait
        // tel quel. C'était la règle du site — avant la réforme d'août 2026,
        // qui a révélé que l'entreprise vendait à son prix d'achat.
        //
        // Le site applique désormais : MAX(prix d'achat) × (1 + taux DALAKOUN).
        // L'API, restée sur MIN sans marge, affichait donc un prix plus bas ET
        // FACTURAIT à ce prix — `prixCatalogue()` sert aussi au calcul des
        // montants (cf. CalculMontant). Une commande passée depuis le mobile
        // rapportait zéro marge.
        //
        // Le MAX plutôt que le MIN : au moment d'afficher, on ignore quel
        // fournisseur servira. Retenir le plus cher garantit la marge quel que
        // soit celui qui livre.
        $prixMax = StockProduit::whereIn('produit_id', $ids)
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->groupBy('produit_id')
            ->selectRaw('produit_id, MAX(prix) AS prix_max')
            ->pluck('prix_max', 'produit_id');

        // Lu UNE fois pour tout le lot : ce taux est demandé sur chaque page du
        // catalogue.
        $tauxGeneral = PourcentageDalakoun::tauxEnVigueur();

        $prix = [];

        foreach (Produit::whereIn('id', $ids)->get(['id', 'prix_moyen', 'pourcentage_dalakoun']) as $produit) {
            $prixAchat = $prixMax[$produit->id] ?? null;

            if ($prixAchat === null) {
                // Aucun fournisseur ne le tarife : pas de coût connu, donc pas
                // de marge à appliquer. On ne fabrique pas un prix — même repli
                // que le site.
                $prix[(int) $produit->id] = (float) ($produit->prix_moyen ?? 0);
                continue;
            }

            // La dérogation du produit prime sur le taux général. Zéro EST une
            // décision : le produit se vend alors à son prix d'achat.
            $taux = $produit->pourcentage_dalakoun !== null
                ? (float) $produit->pourcentage_dalakoun
                : $tauxGeneral;

            $prix[(int) $produit->id] = round(PourcentageDalakoun::appliquerA((float) $prixAchat, $taux));
        }

        return $prix;
    }

    /**
     * FILTRE PAR MONTANT — SUR LE PRIX RÉELLEMENT AFFICHÉ.
     *
     * Le filtre interrogeait la colonne `produit.prix_moyen`. Depuis que le prix
     * de vente se CALCULE — prix d'achat le plus élevé majoré du pourcentage
     * DALAKOUN — cette colonne n'est plus ce que le client voit : une barre de
     * fer affichée 5 136 F y est stockée à 4 800.
     *
     * L'application renvoie les montants qu'elle a AFFICHÉS ; la requête
     * cherchait les montants STOCKÉS. Les deux ne se rencontraient jamais et le
     * filtre ne rendait plus aucun produit.
     *
     * Le tri se fait donc APRÈS le calcul du prix, sur la valeur montrée au
     * client. La liste est plafonnée à quelques milliers de lignes : le coût est
     * sans commune mesure avec celui d'un filtre qui ne marche pas.
     */
    private static function filtrerSurMontant($produits, array $montants)
    {
        if (empty($montants) || $produits === null || count($produits) === 0) {
            return $produits;
        }

        // Comparaison sur des entiers : l'application envoie le montant affiché,
        // arrondi, quand la base porte des décimales.
        $voulus = array_map(fn ($m) => (int) round((float) $m), $montants);

        return $produits->filter(
            fn ($p) => in_array((int) round((float) $p->prix_moyen), $voulus, true)
        )->values();
    }

    public static function appliquerPrixCatalogue($produits, array $montants = [])
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

        // UN SEUL CALCUL, PARTAGE AVEC LA FACTURATION.
        //
        // Cette methode gardait sa PROPRE requete — un MIN(prix) sans marge —
        // alors que prixCatalogue() servait deja au calcul des montants. Les
        // deux ont diverge : l application AFFICHAIT le prix du fournisseur le
        // moins cher et FACTURAIT le prix majore. Un ecart d affichage est
        // genant ; un ecart entre l affichage et la facture ne l est plus.
        //
        // On delegue donc, pour qu il n existe qu une seule reponse a la
        // question « combien coute ce produit ».
        $prix = self::prixCatalogue(array_unique($ids));

        foreach ($produits as $p) {
            if (isset($prix[(int) $p->id])) {
                $p->prix_moyen = (float) $prix[(int) $p->id];
            }
        }

        // Le filtre par montant vient APRÈS, sur le prix qui vient d'être posé :
        // c'est celui que le client a vu, et donc celui qu'il a coché.
        return self::filtrerSurMontant($produits, $montants);
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
