<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Produit;

class ImageProduit extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'image_produit';
    protected $fillable = [
        'image',
        'produit_id',
        'statut',
    ];

    public function produit(){
        return $this->belongsTo(Produit::class);
    }

    public static function lire($id)
    {
        $obj = ImageProduit::find($id);
        $obj->image = Help::urlFichier($obj->image);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new ImageProduit();
    }

    public static function liste($produit_id = null)
    {
        $objs = ImageProduit::when($produit_id, function ($query) use ($produit_id) {
            $query->where('produit_id', $produit_id);
        })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();

        foreach ($objs as $i) {
            $i->image = Help::urlFichier($i->image);
        }
        return $objs;
    }

    public static function enregistrer(array $arr)
    {
        $obj = new ImageProduit($arr);
        if ($obj->save()) return $obj;
        else return new ImageProduit();
    }

    public static function supprimer($id)
    {
        $obj = ImageProduit::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
