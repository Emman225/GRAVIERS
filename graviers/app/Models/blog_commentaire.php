<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\blog;
use App\Models\Client;

class blog_commentaire extends Model
{
    // SoftDeletes : la table porte une colonne deleted_at que le modèle
    // n'exploitait pas. Un commentaire « supprimé » l'était donc définitivement,
    // sans retour possible. Avec ce trait la corbeille fonctionne réellement.
    use HasFactory, SoftDeletes;

    // Statuts de modération, mêmes valeurs que les avis produits (NoteProduit).
    public const EN_ATTENTE = 1;
    public const PUBLIE     = 2;
    public const REFUSE     = 3;

    protected $fillable = [
        'client_id',
        'blog_id',
        'note',
        'commentaire',
        'statut'
    ];

    protected $casts = [
        'note'   => 'integer',
        'statut' => 'integer',
    ];

    public function blog(){
        return $this->belongsTo(blog::class);
    }

    /**
     * L'auteur du commentaire.
     *
     * withDefault() évite une erreur d'affichage si le compte client a été
     * supprimé entre-temps : la modération reste consultable.
     */
    public function client(){
        return $this->belongsTo(Client::class)->withDefault([
            'nom'    => 'Client supprimé',
            'prenom' => '',
        ]);
    }

    public function scopePublies($query){
        return $query->where('statut', self::PUBLIE);
    }

    public function scopeEnAttente($query){
        return $query->where('statut', self::EN_ATTENTE);
    }

    /** Libellé lisible du statut, utilisé par les vues de modération. */
    public function libelleStatut(): string
    {
        return match ((int) $this->statut) {
            self::PUBLIE => 'Publié',
            self::REFUSE => 'Refusé',
            default      => 'En attente',
        };
    }

    /** Classe Bootstrap associée au statut. */
    public function couleurStatut(): string
    {
        return match ((int) $this->statut) {
            self::PUBLIE => 'success',
            self::REFUSE => 'danger',
            default      => 'secondary',
        };
    }
}
