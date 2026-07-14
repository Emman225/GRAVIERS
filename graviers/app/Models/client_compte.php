<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class client_compte extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'compte_id'
    ];

    
}
