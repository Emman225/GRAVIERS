<?php

namespace App\Models;

use App\Models\Livraison;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UniteProduit;

class DetailLivraison extends Model
{
    use HasFactory;

    protected $table = "detail_livraison";
    protected $fillable = [
        'nom_produit',
        'qte',
        // « unite » porte le LIBELLÉ de l'unité (varchar), là où
        // « unite_produit_id » porte sa clé. La colonne est obligatoire en base
        // et sans valeur par défaut, mais elle manquait ici : toute création par
        // affectation de masse l'aurait de toute façon écartée en silence, et
        // l'insertion échouait avec « Field 'unite' doesn't have a default
        // value ». L'API mobile, qui affecte les champs un par un, n'était pas
        // concernée — d'où un défaut visible sur le site seul.
        'unite',
        'unite_produit_id' ,
        'demande_livraison_id',
        'etat_livraison',
        'statut',
        'cout_livraison_id',
        'description',

    ];

    public function coutLivraison(){
        return $this->belongsTo(CoutLivraison::class);
    }

    public function livraisons(){
        return $this->hasMany(Livraison::class);
    }

    public function demandeLivraison(){
        return $this->belongsTo(DemandeLivraison::class);
    }

    public function uniteProduit(){
        return $this->belongsTo(UniteProduit::class);
    }

    /**
     * QUANTITÉ RÉELLEMENT AFFECTÉE À UN CAMION, refus exclus.
     *
     * Le calcul sommait toutes les courses de la ligne, y compris celles que le
     * livreur avait REFUSÉES (accepte = 3). Une seule ligne refusée suffisait
     * alors à faire croire la ligne servie en totalité : l'écran de traitement
     * l'annonçait « Déjà traité », le gestionnaire ne pouvait plus la confier à
     * un autre livreur, et la demande — dont la quantité livrée n'atteindrait
     * jamais celle demandée — restait indéfiniment « en attente ».
     *
     * Une course refusée n'a transporté RIEN. Elle ne consomme donc rien.
     */
    public function qteAffectee(): float
    {
        return (float) $this->livraisons
            ->where('accepte', '!=', Livraison::REFUSEE)
            ->sum('qte');
    }

    /** Ce qu'il reste à confier à un camion sur cette ligne. */
    public function qteRestanteAAffecter(): float
    {
        return max(0, (float) $this->qte - $this->qteAffectee());
    }

    /** La ligne est-elle entièrement confiée à des camions ? */
    public function estEntierementAffectee(): bool
    {
        return $this->qteRestanteAAffecter() <= 0;
    }

    /**
     * Une course a-t-elle été refusée sans avoir été reconfiée depuis ?
     *
     * C'est le signal qui manquait au back-office : la demande restait affichée
     * « EN TRAITEMENT » sans rien dire du refus, et personne ne pouvait savoir
     * qu'une réaffectation était attendue.
     */
    public function attendUneReaffectation(): bool
    {
        return $this->livraisons->where('accepte', Livraison::REFUSEE)->isNotEmpty()
            && $this->qteRestanteAAffecter() > 0;
    }
}
