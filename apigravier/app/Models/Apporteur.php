<?php

namespace App\Models;

use Help;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'piece_recto',
        'piece_verso',
        'numero_piece',
        'mode_paiement_id',
    ];

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

    public static function lireSurCode($code)
    {
        $obj = Apporteur::where('code', $code)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Apporteur();
    }

    public static function liste()
    {
        return Apporteur::where('statut', Help::$STATUT_ACTIF)->get();
    }

    public static function statPaiement($apporteur_id)
    {
        $debutAnnee = date('Y-01-01');
        $debutMois = date('Y-m-01');
        $j = date('d');
        $jp1 = strlen($j) == 1 ? '0'.$j : $j;
        $demain = date("Y-m-$jp1");
        return DB::select("
            SELECT
                ( SELECT sum( montant ) FROM commission_apporteur WHERE statut = 1 AND apporteur_id = $apporteur_id AND created_at BETWEEN '$debutMois 00:00' AND '$demain 23:59' ) AS ce_mois,
                ( SELECT sum( montant ) FROM commission_apporteur WHERE statut = 1 AND apporteur_id = $apporteur_id AND created_at BETWEEN '$debutAnnee 00:00' AND '$demain 23:59' ) AS cette_annee
        ");
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Apporteur($arr);
        if ($obj->save()) return $obj;
        else return new Apporteur();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function supprimer($id)
    {
        $obj = Apporteur::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
