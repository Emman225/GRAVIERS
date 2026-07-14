<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrixPersonnalise extends Model
{
    protected $table = 'prix_personnalises';

    protected $fillable = [
        'client_id',
        'produit_id',
        'prix',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
