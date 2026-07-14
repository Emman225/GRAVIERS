<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Devis;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetailDevis extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'detail_devis';
    protected $fillable = [
        'produit_id',
        'devis_id',
        'qte',
        'prix',
        'statut',
        'prix_fournisseur',
        'cout_livraison',
        'deleted_at'
    ];

    public static function lire($id)
    {
        $obj = DetailDevis::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailDevis();
    }

    public static function lireCle($produit_id, $devis_id)
    {
        $obj = DetailDevis::where('produit_id', $produit_id)
        ->where('devis_id', $devis_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailDevis();
    }

    public static function liste($produit_id = null, $devis_id = null)
    {
        return DetailDevis::when($produit_id, function ($query) use ($produit_id) {
                $query->where('produit_id', $produit_id);
            })
            ->when($devis_id, function ($query) use ($devis_id) {
                $query->where('devis_id', $devis_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new DetailDevis($arr);
        if ($obj->save()) return $obj;
        else return new DetailDevis();
    }

    public static function supprimer($id)
    {
        $obj = DetailDevis::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
    public function devis() : BelongsTo{
        return $this->belongsTo(Devis::class);
    }

    public function produit(){
        return $this->belongsTo(Produit::class);
    }



}
