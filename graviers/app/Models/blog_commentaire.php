<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\blog;

class blog_commentaire extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id',
        'blog_id',
        'note',
        'commentaire',
        'statut'
    ];

    public function blog(){
        return $this->belongsTo(blog::class);
    }
}
