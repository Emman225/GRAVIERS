<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class reduction_appliquee extends Model
{
    use HasFactory;

    protected $fillable = [
        'commande_id',
        'user_id',
        'taux_reduction',
    ];
}
