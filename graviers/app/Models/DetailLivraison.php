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

    /**
     * QUANTITÉ RÉELLEMENT LIVRÉE AU CLIENT sur cette ligne.
     *
     * À ne pas confondre avec la quantité AFFECTÉE : un camion peut être
     * chargé sans avoir encore roulé. Le back-office ne montrait que le reste à
     * confier — un article livré à 20 sur 25 y ressemblait donc à un article
     * dont on n'avait rien fait, alors que le client avait reçu sa marchandise
     * et que le livreur avait clôturé sa course.
     *
     * Seules les courses à l'état LIVREE comptent : une course affectée, en
     * route, ou refusée n'a rien remis au client.
     */
    public function qteLivree(): float
    {
        return (float) $this->livraisons
            ->where('etat_livraison', \Help::$LIVRAISON_LIVREE)
            ->sum('qte');
    }

    /** La marchandise a-t-elle été remise au client, en totalité ? */
    public function estEntierementLivree(): bool
    {
        return $this->qteLivree() >= (float) $this->qte;
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
     * Une course a-t-elle été refusée SANS AVOIR ÉTÉ RECONFIÉE DEPUIS ?
     *
     * C'est le signal qui manquait au back-office : la demande restait affichée
     * « EN TRAITEMENT » sans rien dire du refus, et personne ne pouvait savoir
     * qu'une réaffectation était attendue.
     *
     * « DEPUIS » est le mot important. La règle se contentait d'un refus
     * quelque part et d'une quantité encore à confier — deux faits sans
     * rapport : une ligne de 25 sacs confiée à un camion de 20 en garde 5 à
     * placer, refus ou pas. Le badge restait donc allumé après la
     * réaffectation, et même après la livraison, réclamant un travail déjà
     * fait. Un signal qui crie sans raison finit par ne plus être lu.
     *
     * Un refus est en attente tant qu'AUCUNE course n'a été créée après lui sur
     * cette ligne. La quantité qui reste à placer, elle, se lit à part : c'est
     * « Quantité restant » sur l'écran de traitement.
     */
    public function attendUneReaffectation(): bool
    {
        $refus = $this->livraisons->where('accepte', Livraison::REFUSEE);

        if ($refus->isEmpty()) {
            return false;
        }

        $dernierRefus = $refus->max('id');

        $reconfieeDepuis = $this->livraisons
            ->where('accepte', '!=', Livraison::REFUSEE)
            ->where('id', '>', $dernierRefus)
            ->isNotEmpty();

        return !$reconfieeDepuis && $this->qteRestanteAAffecter() > 0;
    }
}
