<?php

namespace App\Models;

use App\Models\User;
use App\Models\Commande;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PreuveOperation extends Model
{
    use HasFactory;

    protected $table = 'preuve_operation_banque';


    public function commande(){
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

}
