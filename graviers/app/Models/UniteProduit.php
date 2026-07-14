<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniteProduit extends Model
{
    use HasFactory;
    protected $table = 'unite_produit';
    protected $fillable = [
        'abreviation',
        'libelle',
    ];
}
