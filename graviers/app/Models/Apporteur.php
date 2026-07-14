<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Client;

class Apporteur extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'apporteur';
    protected $fillable = [
        'user_id',
        'code',
        'solde',
        'statut',
        'pourcentage',
        'nom',
        'prenom',
        'piece_recto',
        'piece_verso',
        'numero_piece',
        'mode_paiement_id',
        'mode_paiement_prefere',
        'coordonnees_paiement',
        'zone_intervention',
    ];

    public function modePaiement()
    {
        return $this->belongsTo(ModePaiement::class, 'mode_paiement_id');
    }

    public function commissions()
    {
        return $this->hasMany(CommissionApporteur::class, 'apporteur_id');
    }

    public function paiementsApporteur()
    {
        return $this->hasMany(PaiementApporteur::class, 'apporteur_id');
    }

    public static function lire($id)
    {
        $obj = Apporteur::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Apporteur();
    }

    public static function lireSurUser($idUser)
    {
        $obj = Apporteur::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Apporteur();
    }

    public static function liste()
    {
        return Apporteur::selectRaw("apporteur.*, users.nom_prenoms")
            ->orderBy('users.nom_prenoms')
            ->join('users', 'users.id', '=', 'apporteur.user_id')
            ->where('apporteur.statut', Help::$STATUT_ACTIF)->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Apporteur($arr);
        if ($obj->save()) return $obj;
        else return new Apporteur();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'','photo'=>'']);
    }

    public static function supprimer($id)
    {
        $obj = Apporteur::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'code_parrain');
    }
}
