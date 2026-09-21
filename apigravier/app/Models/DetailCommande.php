<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetailCommande extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'detail_commande';
    protected $fillable = [
        'produit_id',
        'commande_id',
        'qte',
        'prix',
        'etat_livraison',
        'statut',
        'qte_livree',
        'prix_fournisseur',
        'reference',
    ];

    public static function lire($id)
    {
        $url = Help::$URL_BASE_FICHIER;
        $obj = DetailCommande::selectRaw("detail_commande.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image")
            ->join('produit', 'produit.id', '=', 'detail_commande.produit_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->where('detail_commande.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailCommande();
    }

    public static function lireCle($produit_id, $commande_id)
    {
        $obj = DetailCommande::where('produit_id', $produit_id)
            ->where('commande_id', $commande_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailCommande();
    }

    /**
     * UNE COMMANDE ANNULEE GARDE SES LIGNES A L'ECRAN.
     *
     * L'annulation depuis le mobile passe chaque ligne a `statut = 2`. Filtree
     * sur `statut = 1`, cette liste revenait alors VIDE : l'ecran de detail du
     * client s'affichait entierement blanc, et son bon de commande s'imprimait
     * sans un seul article. Le client ne pouvait plus savoir ce qu'il avait
     * commande.
     *
     * `$inclureInactives` sert donc aux ecrans de LECTURE. Les appelants qui
     * AGISSENT sur la commande — paiement, annulation — gardent le filtre : ils
     * ne doivent toucher que les lignes vivantes.
     *
     * Une ligne RETIREE de la commande reste exclue dans les deux cas : elle
     * est effacee en douceur (`deleted_at`), et Eloquent l'ecarte de lui-meme.
     */
    public static function liste($produit_id = null, $commande_id = null, $client_id = null, $inclureInactives = false)
    {
        $url = Help::$URL_BASE_FICHIER;
        return DetailCommande::distinct()
            ->selectRaw("detail_commande.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image,
        bl_client.numero as numero_bl,
        bl_client.fichier as fichier_bl")
            ->orderBy('produit.nom', 'asc')
            ->join('produit', 'produit.id', '=', 'detail_commande.produit_id')
            ->join('commande', 'commande.id', '=', 'detail_commande.commande_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->leftjoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('detail_commande.produit_id', $produit_id);
            })
            ->when($commande_id, function ($query) use ($commande_id) {
                $query->where('detail_commande.commande_id', $commande_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('commande.client_id', $client_id);
            })
            ->when(!$inclureInactives, function ($query) {
                $query->where('detail_commande.statut', Help::$STATUT_ACTIF);
            })
            // LA LIGNE D'UNE COMMANDE APPARTIENT À CETTE COMMANDE, QUEL QUE
            // SOIT LE TYPE DE SON PRODUIT.
            //
            // Il y avait ici un filtre `produit.type_affaire = 'VENTE'`. Quand une
            // ligne portait un produit de LOCATION, elle était SILENCIEUSEMENT
            // écartée : l'écran de détail s'ouvrait blanc, le bon s'imprimait vide,
            // et rien ne disait qu'une ligne avait été retirée de la vue.
            //
            // Constaté sur la commande 286453 : 5 « Mini-pelle de chantier » à
            // 99 000 F, soit exactement les 495 000 F affichés — un matériel de
            // location entré dans une commande de vente (voir la garde posée dans
            // ClientController). Le défaut d'origine est en amont ; ce filtre ne
            // faisait que le rendre invisible.
            //
            // `detail_commande` ne contient que des lignes de commande : ce filtre
            // n'écartait donc jamais rien de légitime.
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new DetailCommande($arr);
        if ($obj->save()) return $obj;
        else return new DetailCommande();
    }

    public static function supprimer($id)
    {
        $obj = DetailCommande::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
