<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Livraison;
use App\Models\User;
use App\Models\Vehicule;
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
        'nom_prenoms',
        'longitude',
        'latitude',
        'derniere_position_at',
        'disponible',
        'code',
        'zone_intervention',
        'tarif_km',
        'tarif_forfait_base',
        'mode_tarification',
    ];

    public function paiementsLivreur()
    {
        return $this->hasMany(PaiementLivreur::class, 'livreur_id');
    }

    public static function lire($id)
    {
        $obj = Livreur::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Livreur();
    }

    /**
     * Gain du livreur pour UNE livraison, selon sa tarification (appliquée telle
     * quelle, sans multiplier par le nombre de voyages) :
     *   - mode 'km'   : tarif_km × distance
     *   - mode 'base' : cout_livraison (tarif de base forfaitaire, saisi via le profil)
     * Si la tarification n'est pas configurée (mode/tarif vides ou à 0), on retombe
     * sur $coutGlobal (le calcul global : distance × coût fixe × nombre de voyages).
     */
    public function gainPourLivraison(float $distance, float $coutGlobal = 0): float
    {
        return (float) $this->tarificationLivraison($distance, $coutGlobal)['total'];
    }

    /**
     * Décomposition de la rémunération du livreur pour UNE livraison :
     *   - mode 'km'   : frais_km = tarif_km × distance (forfait_base = 0)
     *   - mode 'base' : forfait_base = cout_livraison  (frais_km = 0)
     *   - non configuré : repli sur le coût global (rangé en forfait_base)
     * Retourne ['forfait_base', 'frais_km', 'total'] pour alimenter les colonnes
     * forfait_base / frais_km / cout_livraison de la livraison.
     */
    public function tarificationLivraison(float $distance, float $coutGlobal = 0): array
    {
        if ($this->mode_tarification === 'km' && (float) $this->tarif_km > 0) {
            $km = (float) $this->tarif_km * $distance;
            return ['forfait_base' => 0.0, 'frais_km' => $km, 'total' => $km];
        }
        if ($this->mode_tarification === 'base' && (float) $this->cout_livraison > 0) {
            $base = (float) $this->cout_livraison;
            return ['forfait_base' => $base, 'frais_km' => 0.0, 'total' => $base];
        }
        // Tarification non configurée -> repli sur le coût global.
        return ['forfait_base' => (float) $coutGlobal, 'frais_km' => 0.0, 'total' => (float) $coutGlobal];
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

    public function user(){
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'','photo'=>'']);
    }
    public function livraisons(){
        return $this->hasMany(Livraison::class);
    }

    public function vehicules(){
        return $this->hasMany(Vehicule::class);
    }

    public function historiquesPrix(){
        return $this->hasMany(HistoriquePrixLivraisonLivreur::class, 'livreur_id')
            ->orderByDesc('created_at');
    }
}
