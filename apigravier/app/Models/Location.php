<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'location';
    protected $fillable = [
        "numero",
        "client_id",
        "mode_paiement_id",
        "adresse_livraison_id",
        "date_location",
        "montant_total",
        "etat_location",
        "note",
        "remise",
        "statut",
        "montant_tva",
        "cout_livraison_client",
        // 0 = « Retrait sur place » : aucun livreur n'intervient, la page de validation
        // gestionnaire (web) n'exige alors ni livreur ni véhicule.
        "est_livrable",
    ];

    // ------------------------------------------------------------------
    //  Montants d'une location — PORTÉS À L'IDENTIQUE depuis le site
    //  (graviers/app/Models/Location.php). Voir la note du modèle Commande :
    //  le plafond doit donner le même chiffre sur les deux canaux.
    // ------------------------------------------------------------------

    public function tvaLocation()
    {
        // Filtre type_affaire : commande_id d'une location et d'une commande
        // peuvent porter la même valeur (tables séparées).
        return $this->hasOne(TvaCommande::class, 'commande_id')
            ->where('type_affaire', Help::$LOCATION)
            ->withDefault(['montant' => 0]);
    }

    /**
     * Total net à payer : HT − remise, puis TVA et livraison.
     * Même formule qu'à la facturation (OrdersController::genererFactureLocation).
     */
    public function montantAPayer(): float
    {
        return max(0, (float) $this->montant_total - (float) ($this->remise ?? 0))
            + (float) ($this->tvaLocation->montant ?? 0)
            + (float) ($this->cout_livraison_client ?? 0);
    }

    /** Ce qui a réellement été encaissé sur la location. */
    public function montantPayeComptant(): float
    {
        return (float) LignePaiement::where('service', Help::$LOCATION)
            ->where('service_id', $this->id)
            ->where('statut', Help::$STATUT_ACTIF)
            ->sum('montant');
    }

    /** Reste dû ; un résidu < 1 fcfa est considéré comme nul. */
    public function montantRestantDu(): float
    {
        $reste = $this->montantAPayer() - $this->montantPayeComptant();
        return $reste < 1 ? 0.0 : $reste;
    }

    public static function lire($id)
    {
        $obj = Location::selectRaw('location.*, mode_paiement.libelle as mode_paiement, tva_commande.montant as montant_tva, adresse_livraison.complement_adresse as adresse')
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'location.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'location.adresse_livraison_id')
            ->leftjoin('tva_commande', function($query) {
                $query->on('tva_commande.commande_id', '=', 'location.id');
                $query->where('tva_commande.type_affaire', Help::$LOCATION);
            })
            ->where('location.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Location();
    }

    public static function lireSurNumero($numero)
    {
        $obj = Location::where('numero', $numero)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Location();
    }

    public static function lireSurDevis($idDevis)
    {
        $obj = Location::where('devis_id', $idDevis)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Location();
    }

    public static function liste($client_id = null, $etat_location = null)
    {
        return Location::selectRaw('location.*, mode_paiement.libelle as mode_paiement, tva_commande.montant as montant_tva, adresse_livraison.complement_adresse as adresse')
            ->orderBy('location.id', 'desc')
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'location.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'location.adresse_livraison_id')
            ->leftjoin('tva_commande', function($query) {
                $query->on('tva_commande.commande_id', '=', 'location.id');
                $query->where('tva_commande.type_affaire', Help::$LOCATION);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('location.client_id', $client_id);
            })
            ->when($etat_location, function ($query) use ($etat_location) {
                $query->where('location.etat_location', $etat_location);
            })
            // location.statut est surchargé : 1 = active, 3 = soldée (payée), 2 = INACTIF.
            // Le callback de paiement (web ET mobile) met statut = 3 quand la location est
            // entièrement payée. Filtrer uniquement statut = 1 faisait DISPARAÎTRE de la
            // liste toute location soldée. On affiche donc active + soldée, on exclut INACTIF.
            ->whereIn('location.statut', [Help::$STATUT_ACTIF, 3])
            ->limit(500)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new location($arr);
        if ($obj->save()) return $obj;
        else return new Location();
    }

    public static function modifierEtatlocation($idlocation, $newEtat)
    {
        $obj = Location::lire($idlocation);
        $obj->etat_location = $newEtat;
        $obj->save();
        return $obj;
    }

    public static function supprimer($id)
    {
        $obj = Location::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
