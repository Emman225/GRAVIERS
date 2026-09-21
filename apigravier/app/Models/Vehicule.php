<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicule extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'vehicule';
    protected $fillable = [
        "immatriculation",
        "nom",
        "description",
        "type_vehicule_id",
        "livreur_id",
        "statut",
        "disponible",
        "capacite",
        "marque",
        "modele",
    ];

    /**
     * Rend le véhicule à nouveau disponible une fois ses courses achevées.
     *
     * L'affectation d'une demande de livraison passe « disponible » à 0, mais
     * RIEN ne le remettait à 1 : le véhicule restait bloqué indéfiniment après
     * sa première course, et le gestionnaire se retrouvait sans aucun véhicule
     * à proposer alors que les livreurs avaient terminé.
     *
     * On ne libère que s'il ne reste AUCUNE course en cours sur ce véhicule :
     * il peut porter plusieurs livraisons, en achever une ne le libère pas.
     *
     * Renvoie true si le véhicule vient d'être libéré.
     */
    public static function libererSiPlusAucuneCourse($vehiculeId): bool
    {
        if (empty($vehiculeId)) {
            return false;
        }

        $vehicule = Vehicule::find($vehiculeId);
        if (!$vehicule) {
            return false;
        }

        // UNE COURSE REFUSÉE N'EST PAS UNE COURSE EN COURS.
        //
        // Le refus laisse la course en base, active et non livrée — elle ne le
        // sera jamais. Comptée ici, elle retenait le camion pour toujours : une
        // seule course refusée dans son histoire, et il ne redevenait plus
        // jamais disponible, même après avoir terminé toutes les autres.
        //
        // Même règle que le site (Vehicule de graviers) : la clôture se fait
        // depuis l'application OU depuis le site, et ne corriger qu'un des deux
        // ferait dépendre l'état du camion du canal employé.
        //
        // `accepte` vaut 3 pour un refus. La valeur est écrite en clair ici :
        // ce projet n'a pas de constante côté API.
        $courseEnCours = Livraison::where('vehicule_id', $vehiculeId)
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('accepte', '!=', 3)
            ->where('etat_livraison', '!=', Help::$LIVRAISON_LIVREE)
            ->exists();

        if ($courseEnCours) {
            return false;
        }

        $vehicule->disponible = 1;
        $vehicule->save();

        return true;
    }

    public static function lire($id)
    {
        $obj = Vehicule::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Vehicule();
    }

    public static function liste($livreur_id = null, $type_vehicule_id = null)
    {
        return Vehicule::orderBy('vehicule.nom')
        ->selectRaw('vehicule.*, type_vehicule.libelle as type_vehicule')
        ->join('type_vehicule', 'type_vehicule.id', '=', 'vehicule.type_vehicule_id')
            ->when($livreur_id, function ($query) use ($livreur_id) {
                $query->where('vehicule.livreur_id', $livreur_id);
            })
            ->when($type_vehicule_id, function ($query) use ($type_vehicule_id) {
                $query->where('vehicule.type_vehicule_id', $type_vehicule_id);
            })
            ->where('vehicule.statut', Help::$STATUT_ACTIF)
            ->get();
    }
}
