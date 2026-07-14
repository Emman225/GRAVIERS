<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Compte extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id'
    ];

    public function clients(){
        return $this->belongsToMany(Client::class,'client_comptes');
    }
}
