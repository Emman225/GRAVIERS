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
    public function prixPour(?Client $client = null): float
    {
        if ($client && $client->id) {
            $perso = PrixPersonnalise::where('client_id', $client->id)
                ->where('produit_id', $this->id)
                ->first();
            if ($perso) return (float) $perso->prix;
        }

        // Prix catalogue = prix fournisseur le plus bas (stock actif, prix > 0),
        // cohérent avec l'affichage accueil/recherche. Le panier/commande facture
        // donc le même prix que celui montré au client. Fallback sur prix_moyen
        // si aucun fournisseur n'a défini de prix.
        $prixFournisseur = StockProduit::where('produit_id', $this->id)
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->min('prix');

        if ($prixFournisseur !== null) {
            return (float) $prixFournisseur;
        }

        return (float) $this->prix_moyen;
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
