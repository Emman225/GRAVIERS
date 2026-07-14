<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Location;
use App\Models\Produit;

class DetailLocation extends Model
{
    use HasFactory;

    public $table = "detail_location";
    protected $fillable = [
        'produit_id',
        'location_id',
        'qte',
        'debut',
        'fin',
        'prix',
        'etat_location',
        'statut',
        'nombre_jour'
    ];

    public function location(){
        return $this->belongsTo(Location::class);
    }

    public function produit(){
        return $this->belongsTo(Produit::class);
    }
}
