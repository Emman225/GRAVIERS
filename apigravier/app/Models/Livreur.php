<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Livraison;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Livreur extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'livreur';
    protected $fillable = [
        'user_id',
        'num_piece_identite',
        'piece_recto',
        'piece_verso',
        'statut',
        'solde',
        'cout_livraison',
        'longitude',
        'latitude',
        'derniere_position_at',
        'disponible',
    ];

    /**
     * Calcule la distance Haversine en km entre deux points GPS.
     * Retourne null si les coordonnées sont invalides.
     */
    public static function distanceKm($lat1, $lon1, $lat2, $lon2): ?float
    {
        $lat1 = is_numeric($lat1) ? (float) $lat1 : null;
        $lon1 = is_numeric($lon1) ? (float) $lon1 : null;
        $lat2 = is_numeric($lat2) ? (float) $lat2 : null;
        $lon2 = is_numeric($lon2) ? (float) $lon2 : null;
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }
        $rayonTerre = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $rayonTerre * $c;
    }

    public static function lire($id)
    {
        $obj = Livreur::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Livreur();
    }


    public static function lireSurUser($idUser)
    {
        $obj = Livreur::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Livreur();
    }

    public static function liste($num_piece_identite = null)
    {
        return Livreur::when($num_piece_identite, function ($query) use ($num_piece_identite) {
                $query->where('num_piece_identite', $num_piece_identite);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Livreur($arr);
        if ($obj->save()) return $obj;
        else return new Livreur();
    }

    public static function supprimer($id)
    {
        $obj = Livreur::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function User(){
        return $this->belongsTo(User::class);
    }
    public function livraisons(){
        return $this->hasMany(Livraison::class);
    }
}
