<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeReset extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'email',
        'user_id',
    ];
}
