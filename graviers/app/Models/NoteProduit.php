<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Produit;
use App\Models\Client;

class NoteProduit extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'note_produit';
    protected $fillable = [
        'produit_id',
        'client_id',
        'avis',
        'note',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = NoteProduit::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new NoteProduit();
    }

    public static function lireCle($produit_id, $client_id)
    {
        $obj = NoteProduit::where('produit_id', $produit_id)
        ->where('client_id', $client_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new NoteProduit();
    }

    public static function liste($produit_id = null, $client_id = null)
    {
        return NoteProduit::orderBy('note', 'desc')
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('produit_id', $produit_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('client_id', $client_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new NoteProduit($arr);
        if ($obj->save()) return $obj;
        else return new NoteProduit();
    }

    public static function supprimer($id)
    {
        $obj = NoteProduit::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function client(){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }
    public function produit(){
        return $this->belongsTo(Produit::class);
    }
}
