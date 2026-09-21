<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Commande;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Fournisseur;
use App\Models\Enlevement;
use App\Models\UniteProduit;
use App\Models\Categorie;
use App\Models\Livraison;
use App\Models\Client;
use App\Models\PrixPersonnalise;
use Illuminate\Support\Facades\Auth;

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
        'unite_produit_id',
        'prix_fournisseur',
        // Dérogation au taux général, produit par produit. NULL = pas de dérogation.
        'pourcentage_dalakoun',
        'caution',
        'deleted_at'

    ];

    public static function lire($id)
    {
        $obj = Produit::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Produit();
    }

    public function categories(){
        return $this->belongsToMany(Categorie::class,'categorie_produit')->withPivot('categorie_id','produit_id');
    }

    public function fournisseurs(){
        return $this->belongsToMany(Fournisseur::class,'stock_produit')->withPivot('qte','prix','seuil_alert');
    }

    /**
     * Limite aux produits qui "appartiennent" à au moins un fournisseur :
     * une ligne stock_produit active (statut actif, non supprimée). Utilisé par
     * le catalogue (accueil, recherche, catégories, location) pour n'afficher
     * que les produits réellement approvisionnés par un fournisseur.
     */
    public function scopeAvecFournisseur($query)
    {
        return $query->whereExists(function ($q) {
            $q->select(\DB::raw(1))
              ->from('stock_produit')
              ->whereColumn('stock_produit.produit_id', 'produit.id')
              ->where('stock_produit.statut', Help::$STATUT_ACTIF)
              ->whereNull('stock_produit.deleted_at');
        });
    }

    public function client(){
        return $this->belongsToMany(Client::class,'likes')->withPivot('id','created_at','updated_at');
    }

    public function notes(){
        return $this->belongsToMany(Client::class,'note_produit')->withPivot('produit_id','client_id','note','avis','created_at','statut');
    }

    public function commandes(){
        return $this->belongsToMany(Commande::class,'detail_commande')->withPivot('id','qte','prix','statut');
    }

    public function enlevements(){
        return $this->hasMany(Enlevement::class);
    }
    
    public function livraisons(){
        return $this->hasMany(Livraison::class);
    }

    public function UniteProduit(){
        return $this->belongsTo(UniteProduit::class);
    }

    public function image(){
        return $this->hasMany(ImageProduit::class);
    }

    public static function lireReference($reference)
    {
        $obj = Produit::where('reference', $reference)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Produit();
    }

    public static function liste($search = null)
    {
        return Produit::orderBy('nom', 'asc')
            ->when($search, function ($query) use ($search) {
                $query->where('reference', $search);
                $query->orWhere('nom', 'LIKE', "%$search%");
                $query->orWhere('abreviation', 'LIKE', "%$search%");
                $query->orWhere('description', 'LIKE', "%$search%");
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Produit($arr);
        if ($obj->save()) return $obj;
        else return new Produit();
    }

    public static function supprimer($id)
    {
        $obj = Produit::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    /**
     * Retourne le prix unitaire à appliquer pour ce produit pour un client donné.
     * - Si le client a un PrixPersonnalise pour ce produit → ce prix
     * - Sinon → prix_moyen du produit
     *
     * Source de vérité unique pour TOUS les calculs métier (paniers, devis, commandes,
     * factures, exports). Aucun autre code ne doit lire `prix_moyen` directement quand
     * un client est en jeu.
     */
    /**
     * ALIGNE LE PRIX AFFICHÉ SUR CELUI QUE LE PANIER FACTURERA.
     *
     * Le catalogue montrait `produit.prix_moyen` pendant que le panier retenait
     * prixPour() — le prix fournisseur le plus bas parmi les stocks actifs. Les
     * deux ne coïncident que par hasard : une bétonnière annoncée à 20 000 se
     * retrouvait au panier à 100, et une brique annoncée à 150 y passait à
     * 5 000. Le client ne payait pas le prix qu'on lui avait montré, dans un
     * sens comme dans l'autre.
     *
     * L'alignement se faisait déjà sur l'accueil et la recherche, recopié à
     * l'identique dans chacun ; il manquait à la location, à la page d'accueil
     * secondaire et aux pages de catégorie. Il est désormais écrit une fois.
     *
     * UNE SEULE REQUÊTE pour toute la liste : appelée par produit, prixPour()
     * en déclencherait une par article — 80 requêtes pour un catalogue de 80.
     *
     * Surcharge d'AFFICHAGE, jamais persistée : le prix métier reste prixPour().
     */
    /**
     * LE PRIX DE VENTE DE PLUSIEURS PRODUITS, indexé par identifiant.
     *
     * Pour les appelants qui n'ont que des identifiants en main — un devis, un
     * écran de prix négociés. Ceux qui disposent des produits eux-mêmes passent
     * par alignerPrixAffiche(), qui lit leur dérogation sans requête de plus.
     *
     * Quatre écrans calculaient encore le prix à leur façon, chacun avec sa
     * copie de la règle et tous sur le fournisseur LE MOINS cher. L'accueil de
     * la boutique en faisait partie : après la mise en service du pourcentage,
     * il affichait toujours 100 F un gravier vendu 14 850, parce que sa propre
     * ligne de code écrasait le prix calculé.
     */
    /**
     * PRIX D'ACHAT MANIFESTEMENT HORS DE PROPORTION dans leur famille.
     *
     * Depuis que le prix de vente se calcule à partir du prix d'achat, une
     * faute de frappe sur ce dernier n'affecte plus seulement ce qu'on verse au
     * fournisseur : elle fait directement le prix montré au client. Une barre
     * de fer saisie 50 000 au lieu de 5 000 s'est ainsi retrouvée affichée
     * 55 000 en boutique, entre des voisines à 2 200 et 7 480.
     *
     * On compare chaque produit à la MÉDIANE de sa catégorie — insensible aux
     * valeurs extrêmes, contrairement à la moyenne, que l'aberration elle-même
     * tirerait vers le haut. Au-delà d'un facteur 3 dans un sens ou dans
     * l'autre, la ligne est signalée.
     *
     * LES SEUILS SONT ASYMÉTRIQUES, et réglés sur le catalogue réel.
     *
     * Trop CHER : au-delà de 2,5 fois la médiane. La faute de frappe courante
     * est le zéro de trop, et un prix d'achat gonflé se retourne aussitôt
     * contre le client. Le sable de mer à 25 000 pour une médiane de 9 000
     * ressort à 2,8 — il passait sous un seuil de 3.
     *
     * Trop BAS : en deçà du quart de la médiane seulement. L'écart vers le bas
     * est souvent légitime — une barre de 6 mm coûte naturellement trois fois
     * moins qu'une de 12 mm, et un seuil symétrique l'aurait signalée à tort.
     *
     * C'est un signal, pas un verdict : un produit peut légitimement coûter
     * bien plus que ses voisins. On alerte, on ne bloque pas.
     */
    public static function prixAchatSuspects(float $facteurHaut = 2.5, float $facteurBas = 4.0): array
    {
        $achats = StockProduit::where('statut', Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->groupBy('produit_id')
            ->selectRaw('produit_id, MAX(prix) AS mx')
            ->pluck('mx', 'produit_id');

        if ($achats->isEmpty()) {
            return [];
        }

        $produits = static::with('categories')->whereIn('id', $achats->keys())->get();

        // Les prix d'achat regroupés par catégorie.
        $parCategorie = [];

        foreach ($produits as $produit) {
            foreach ($produit->categories as $categorie) {
                $parCategorie[$categorie->id]['nom'] = $categorie->nom;
                $parCategorie[$categorie->id]['prix'][] = (float) $achats[$produit->id];
            }
        }

        $suspects = [];

        foreach ($produits as $produit) {
            $prix = (float) $achats[$produit->id];

            foreach ($produit->categories as $categorie) {
                $voisins = $parCategorie[$categorie->id]['prix'] ?? [];

                // Une famille de moins de trois produits ne dit rien : la
                // médiane y serait le produit lui-même, ou son unique voisin.
                if (count($voisins) < 3) {
                    continue;
                }

                $mediane = static::mediane($voisins);

                if ($mediane <= 0) {
                    continue;
                }

                $rapport = $prix / $mediane;

                if ($rapport >= $facteurHaut || $rapport <= 1 / $facteurBas) {
                    // QUI porte ce prix, et où le corriger. Un prix d'achat
                    // appartient à un couple produit × fournisseur : sans le
                    // nom du fournisseur, il faut le chercher à la main.
                    $ligne = StockProduit::with('fournisseur')
                        ->where('produit_id', $produit->id)
                        ->where('statut', Help::$STATUT_ACTIF)
                        ->whereNull('deleted_at')
                        ->orderByDesc('prix')
                        ->first();

                    $suspects[$produit->id] = [
                        'id'             => $produit->id,
                        'nom'            => $produit->nom,
                        'categorie'      => $categorie->nom,
                        'prix'           => $prix,
                        'mediane'        => $mediane,
                        'rapport'        => $rapport,
                        'fournisseur'    => $ligne?->fournisseur?->nom_prenoms ?: '—',
                        'fournisseur_id' => $ligne?->fournisseur_id,
                    ];
                    break;
                }
            }
        }

        return array_values($suspects);
    }

    private static function mediane(array $valeurs): float
    {
        sort($valeurs);
        $n = count($valeurs);

        if ($n === 0) {
            return 0.0;
        }

        $milieu = intdiv($n, 2);

        return $n % 2 === 1
            ? (float) $valeurs[$milieu]
            : ((float) $valeurs[$milieu - 1] + (float) $valeurs[$milieu]) / 2;
    }

    public static function prixVenteParProduit($produitIds = null): array
    {
        $requete = StockProduit::where('statut', Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at');

        if ($produitIds !== null) {
            $requete->whereIn('produit_id', $produitIds);
        }

        $prixAchat = $requete->groupBy('produit_id')
            ->selectRaw('produit_id, MAX(prix) AS mx')
            ->pluck('mx', 'produit_id');

        if ($prixAchat->isEmpty()) {
            return [];
        }

        $tauxGeneral = PourcentageDalakoun::tauxEnVigueur();

        $derogations = static::whereIn('id', $prixAchat->keys())
            ->whereNotNull('pourcentage_dalakoun')
            ->pluck('pourcentage_dalakoun', 'id');

        $prix = [];

        foreach ($prixAchat as $id => $achat) {
            $taux = $derogations[$id] ?? $tauxGeneral;
            $prix[$id] = (float) $achat * (1 + (float) $taux / 100);
        }

        return $prix;
    }

    public static function alignerPrixAffiche($produits): void
    {
        $liste = $produits instanceof \Illuminate\Pagination\AbstractPaginator
            ? $produits->getCollection()
            : collect($produits);

        if ($liste->isEmpty()) {
            return;
        }

        // Le fournisseur le PLUS CHER fait le prix : c'est lui qui garantit le
        // taux annoncé quel que soit celui qui servira le bon.
        $prixAchat = StockProduit::whereIn('produit_id', $liste->pluck('id'))
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->groupBy('produit_id')
            ->selectRaw('produit_id, MAX(prix) AS mx')
            ->pluck('mx', 'produit_id');

        // Le taux général est lu UNE fois pour toute la liste : le relire par
        // produit ferait une requête par article du catalogue.
        $tauxGeneral = PourcentageDalakoun::tauxEnVigueur();

        foreach ($liste as $produit) {
            if (!isset($prixAchat[$produit->id])) {
                continue;
            }

            $taux = $produit->pourcentage_dalakoun !== null
                ? (float) $produit->pourcentage_dalakoun
                : $tauxGeneral;

            $produit->prix_moyen = (float) $prixAchat[$produit->id] * (1 + $taux / 100);
        }
    }

    public function prixPour(?Client $client = null): float
    {
        if ($client && $client->id) {
            $perso = PrixPersonnalise::where('client_id', $client->id)
                ->where('produit_id', $this->id)
                ->first();
            if ($perso) return (float) $perso->prix;
        }

        // LE PRIX DE VENTE EST CALCULÉ : prix d'achat × (1 + pourcentage DALAKOUN).
        //
        // Il valait jusqu'ici le prix d'achat lui-même — l'écran d'ajout de
        // produit recopie « Prix fournisseur » dans la ligne de stock, et c'est
        // cette ligne qui faisait le prix affiché. L'entreprise vendait donc au
        // prix auquel elle achetait.
        //
        // ON RETIENT LE FOURNISSEUR LE PLUS CHER, et non le moins-disant.
        // Sur les graviers et sables, l'écart entre fournisseurs est
        // systématique : 7 %. En se basant sur le moins cher, un bon parti chez
        // l'autre ramenait la marge de 20 % à 12 % — un tiers perdu, sans que
        // rien ne le signale. Le plus cher garantit le taux annoncé quel que
        // soit le fournisseur retenu.
        $prixAchat = static::prixAchatDe($this->id);

        if ($prixAchat === null) {
            // Aucun fournisseur ne l'a tarifé : impossible de calculer une
            // marge sur un coût inconnu. On rend le prix catalogue tel quel
            // plutôt que d'inventer un montant.
            return (float) $this->prix_moyen;
        }

        return PourcentageDalakoun::appliquerA($prixAchat, $this->tauxDalakoun());
    }

    /**
     * Le taux qui s'applique à CE produit : sa dérogation si elle existe, le
     * taux général sinon.
     *
     * Une dérogation à zéro est une décision — « vendu au prix d'achat » — et
     * se distingue donc de l'absence de dérogation.
     */
    public function tauxDalakoun(): float
    {
        if ($this->pourcentage_dalakoun !== null) {
            return (float) $this->pourcentage_dalakoun;
        }

        return PourcentageDalakoun::tauxEnVigueur();
    }

    /**
     * LE PRIX D'ACHAT D'UN PRODUIT : le plus élevé parmi ses fournisseurs actifs.
     *
     * Null si aucun fournisseur ne l'a tarifé — un coût inconnu ne se devine
     * pas, et le distinguer de zéro évite d'annoncer une marge imaginaire.
     */
    public static function prixAchatDe(int $produitId): ?float
    {
        $prix = StockProduit::where('produit_id', $produitId)
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->max('prix');

        return $prix === null ? null : (float) $prix;
    }

    /**
     * Retourne le client connecté (si l'utilisateur connecté est de type client).
     * Utilisé par les helpers d'affichage pour récupérer automatiquement le bon prix.
     */
    public static function clientCourant(): ?Client
    {
        if (!Auth::check()) return null;
        $client = Client::where('user_id', Auth::id())->first();
        return $client && $client->id ? $client : null;
    }

    /**
     * Charge le tableau associatif [produit_id => prix_personnalise] pour un client.
     * Format compatible avec les vues existantes qui font `isset($prixPerso[$id])`.
     */
    public static function prixPersonnalisesPour(?Client $client): array
    {
        if (!$client || !$client->id) return [];
        return PrixPersonnalise::where('client_id', $client->id)
            ->pluck('prix', 'produit_id')
            ->toArray();
    }
}
