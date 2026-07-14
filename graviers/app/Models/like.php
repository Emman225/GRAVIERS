<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class like extends Model
{
    use HasFactory;


    protected $fillable = [
        'client_id',
        'produit_id',
        'deleted_at'
    ];


}
