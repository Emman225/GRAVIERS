<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\LignePaiement;

class ModePaiement extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mode_paiement';
    protected $fillable = [
        'libelle',
        'description',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = ModePaiement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new ModePaiement();
    }

    public static function liste()
    {
        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('en_ligne', 1)
            ->get();
    }

    /**
     * Moyens de règlement en ligne proposés au CLIENT dans l'application.
     *
     * liste() ne retenait que en_ligne = 1, en supposant qu'un instrument bancaire
     * n'y soit jamais. Le drapeau n'est pas fiable : en production « Carte
     * bancaire » et « Virement bancaire » y sont à 1, et l'application les
     * proposait donc comme s'ils passaient par la passerelle mobile money.
     *
     * Un virement ou une carte, c'est l'agent qui les constate à l'encaissement.
     * Ils sont écartés par leur LIBELLÉ, qui dit ce que la chose EST, et non par un
     * drapeau saisi au back-office. Même règle que sur le site
     * (ModePaiement::listePourClient de graviers).
     *
     * Le règlement en agence n'apparaît pas ici : l'application le traite par son
     * propre indicateur (mode_paiement = 2), pas par un mode de cette table.
     */
    public static function listePourClient()
    {
        $instrumentsReservesAgent = ['virement', 'chèque', 'cheque', 'espèce', 'espece', 'carte'];

        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('en_ligne', 1)
            ->where(function ($query) use ($instrumentsReservesAgent) {
                foreach ($instrumentsReservesAgent as $motif) {
                    $query->where('libelle', 'not like', '%' . $motif . '%');
                }
            })
            ->get();
    }

    public static function listeTous()
    {
        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    // Modes proposés à l'apporteur d'affaire (préférence de versement de commission) :
    // on exclut les modes "en agence" qui n'ont pas de sens pour un versement.
    public static function listePourApporteur()
    {
        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('libelle', 'not like', '%agence%')
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new ModePaiement($arr);
        if ($obj->save()) return $obj;
        else return new ModePaiement();
    }

    public static function supprimer($id)
    {
        $obj = ModePaiement::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
    public function lignePaiement(){
        return $this->hasMany(LignePaiement::class);
    }
}
