<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PreuveOperationBanque extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'preuve_operation_banque';
    protected $fillable = [
        'client_id',
        'commande_id',
        'reference',
        'num_compte',
        'banque',
        'date_operation',
        'est_valide',
        'note_supp',
        'fichier',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = PreuveOperationBanque::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new PreuveOperationBanque();
    }

    public static function lireReference($reference)
    {
        $obj = PreuveOperationBanque::where('reference', $reference)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new PreuveOperationBanque();
    }

    public static function liste($client_id = null, $commande_id = null, $num_compte = null, $banque = null)
    {
        return PreuveOperationBanque::orderBy('nom', 'asc')
            ->when($client_id, function ($query) use ($client_id) {
                    $query->where('client_id', $client_id);
                })
            ->when($commande_id, function ($query) use ($commande_id) {
                    $query->where('commande_id', $commande_id);
                })
            ->when($num_compte, function ($query) use ($num_compte) {
                    $query->where('num_compte', $num_compte);
                })
            ->when($banque, function ($query) use ($banque) {
                    $query->where('banque', $banque);
                })
            ->where('statut', Help::$STATUT_ACTIF)
            ->limit(500)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new PreuveOperationBanque($arr);
        if ($obj->save()) return $obj;
        else return new PreuveOperationBanque();
    }

    public static function supprimer($id)
    {
        $obj = PreuveOperationBanque::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
