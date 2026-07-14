<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Client;

class blog extends Model
{
    use HasFactory;

    protected $table = "blog";
    protected $fillable = [
        'image',
        'titre',
        'description',
        'user_publie_id',
        'user_vu_id',
        'image_detail',
        'statut',
        'publie'
    ];

    public function clients(){
        return $this->belongsToMany(Client::class,'blog_commentaires')->withPivot('note','commentaire','created_at','updated_at','id','statut');
    }
}
