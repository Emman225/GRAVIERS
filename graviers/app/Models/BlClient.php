<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Commande;
class BlClient extends Model
{
    use HasFactory;

    protected $table = 'bl_client';
    protected $fillable = [
        'numero',
        'client_id',
        'montant',
        'fichier',
        'commande_id'
       
    ];

    public function commande(){
        return $this->belongsTo(Commande::class,'commande_id');
    }
}
