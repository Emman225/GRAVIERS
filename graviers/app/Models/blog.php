<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Client;

class blog extends Model
{
    // SoftDeletes : la table porte déjà une colonne deleted_at, mais le modèle ne
    // l'exploitait pas. Un blog « supprimé » restait donc visible partout, y
    // compris sur le site public, et delete() effaçait définitivement la ligne.
    // Avec ce trait, la corbeille fonctionne réellement et les blogs supprimés
    // disparaissent des listes sans être perdus.
    use HasFactory, SoftDeletes;

    // La table s'appelle « blogs » : c'est celle que crée la migration d'origine et
    // que référence la clé étrangère de blog_commentaires. Le modèle visait « blog »,
    // table inexistante en production — d'où l'erreur « Table 'blog' doesn't exist »
    // sur la liste des blogs.
    protected $table = "blogs";
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

    protected $casts = [
        'publie' => 'boolean',
        'vu'     => 'integer',
    ];

    public function clients(){
        return $this->belongsToMany(Client::class,'blog_commentaires')->withPivot('note','commentaire','created_at','updated_at','id','statut');
    }

    /**
     * Les commentaires de l'article.
     *
     * À préférer à clients() : une table pivot ne connaît pas les suppressions
     * douces, si bien qu'un commentaire mis à la corbeille restait visible à
     * travers clients(). Ici le modèle blog_commentaire applique SoftDeletes.
     */
    public function commentaires(){
        // Pas de tri ici : la relation sert aussi à withCount(), et un ORDER BY
        // se retrouverait inutilement dans la sous-requête de comptage. Le tri
        // est appliqué là où la liste est réellement lue.
        return $this->hasMany(blog_commentaire::class, 'blog_id');
    }

    /** Les seuls commentaires visibles du grand public : ceux validés. */
    public function commentairesPublies(){
        return $this->commentaires()->where('statut', blog_commentaire::PUBLIE);
    }

    /** Note moyenne des commentaires publiés, sur 5 ; null s'il n'y en a aucun. */
    public function noteMoyenne(): ?float
    {
        $notes = $this->commentairesPublies()->whereNotNull('note')->pluck('note');

        return $notes->isEmpty() ? null : round($notes->avg(), 1);
    }
}
