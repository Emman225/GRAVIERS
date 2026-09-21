<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetailLocation extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'detail_location';
    protected $fillable = [
        "produit_id",
        "location_id",
        "qte",
        "debut",
        "fin",
        "prix",
        "etat_location",
        "statut",
    ];

    public static function lire($id)
    {
        $url = Help::$URL_BASE_FICHIER;
        $obj = DetailLocation::selectRaw("detail_location.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image")
            ->join('produit', 'produit.id', '=', 'detail_location.produit_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->where('detail_location.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailLocation();
    }

    public static function lireCle($produit_id, $location_id)
    {
        $obj = DetailLocation::where('produit_id', $produit_id)
            ->where('location_id', $location_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailLocation();
    }

    /**
     * UNE LOCATION ANNULEE GARDE SES LIGNES A L'ECRAN.
     *
     * L'annulation depuis le mobile passe chaque ligne a `statut = 2`. Filtree
     * sur `statut = 1`, cette liste revenait alors VIDE : l'ecran de detail du
     * client s'affichait entierement blanc, et son bon de location s'imprimait
     * sans un seul article. Le client ne pouvait plus savoir ce qu'il avait
     * loue.
     *
     * `$inclureInactives` sert donc aux ecrans de LECTURE. Les appelants qui
     * AGISSENT sur la location — paiement, annulation — gardent le filtre : ils
     * ne doivent toucher que les lignes vivantes.
     *
     * Une ligne RETIREE de la location reste exclue dans les deux cas : elle
     * est effacee en douceur (`deleted_at`), et Eloquent l'ecarte de lui-meme.
     */
    public static function liste($produit_id = null, $location_id = null, $client_id = null, $inclureInactives = false)
    {
        $url = Help::$URL_BASE_FICHIER;
        return DetailLocation::distinct()
            // detail_location.etat_location est écrit « EN ATTENTE » à la création de la
            // location et n'est mis à jour NULLE PART ensuite (ni le web validerLocation/
            // retourLocation, ni le mobile ne touchent les lignes : ils ne font évoluer que
            // location.etat_location). La colonne restait donc figée, et l'app affichait
            // « (EN ATTENTE) » sur une location TERMINEE — en bloquant au passage la
            // notation du matériel (« L'article n'a pas encore été livré ! »).
            // On renvoie donc l'état réel, celui de la location. L'alias est volontairement
            // placé APRÈS detail_location.* pour écraser la colonne périmée.
            ->selectRaw("detail_location.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image,
        location.etat_location as etat_location")
            ->orderBy('produit.nom', 'asc')
            ->join('produit', 'produit.id', '=', 'detail_location.produit_id')
            ->join('location', 'location.id', '=', 'detail_location.location_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('detail_location.produit_id', $produit_id);
            })
            ->when($location_id, function ($query) use ($location_id) {
                $query->where('detail_location.location_id', $location_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('location.client_id', $client_id);
            })
            ->when(!$inclureInactives, function ($query) {
                $query->where('detail_location.statut', Help::$STATUT_ACTIF);
            })
            // LA LIGNE D'UNE LOCATION APPARTIENT À CETTE LOCATION, QUEL QUE
            // SOIT LE TYPE DE SON PRODUIT.
            //
            // Filtre symétrique de celui retiré dans DetailCommande, et porteur du
            // même risque : une ligne dont le produit n'est pas typé LOCATION était
            // silencieusement écartée, et l'écran de détail s'ouvrait blanc sans
            // que rien ne dise qu'une ligne manquait.
            //
            // `detail_location` ne contient que des lignes de location : ce filtre
            // n'écartait donc jamais rien de légitime.
            ->get();
    }

    public static function supprimer($id)
    {
        $obj = DetailLocation::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
