<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ModePaiement;

class LignePaiement extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'ligne_paiement';
    protected $fillable = [
        'code_paiement',
        'paiement_id',
        'mode_paiement_id',
        'reference',
        'moyen_paiement',
        'date_paiement',
        'montant',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = LignePaiement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new LignePaiement();
    }

    public static function listeSurCode($code)
    {
        return LignePaiement::distinct()->selectRaw('ligne_paiement.*, paiement.libelle, client.nom, client.email, paiement.client_id, client.parrain_id, client.client_a_terme,
        client.contact1, users.adresse, pays.nom as pays, ville.nom as ville, gestionnaire.nom_prenoms as gestionnaire')
            ->join('paiement', 'ligne_paiement.paiement_id', 'paiement.id')
            ->join('client', 'client.id', 'paiement.client_id')
            ->join('users', 'client.user_id', 'users.id')
            ->leftJoin('users as gestionnaire', 'ligne_paiement.user_id', 'gestionnaire.id')
            ->leftJoin('pays', 'users.pays_id', 'pays.id')
            ->leftJoin('ville', 'users.ville_id', 'ville.id')
            ->where('ligne_paiement.code_paiement', $code)
            ->get();
    }

    public static function listeSurIdPaiement($idPaiement)
    {
        return LignePaiement::distinct()->selectRaw('ligne_paiement.*, paiement.libelle, client.nom, client.email, paiement.client_id, client.parrain_id, client.client_a_terme,
        client.contact1, users.adresse, pays.nom as pays, ville.nom as ville, gestionnaire.nom_prenoms as gestionnaire')
            ->join('paiement', 'ligne_paiement.paiement_id', 'paiement.id')
            ->join('client', 'client.id', 'paiement.client_id')
            ->join('users', 'client.user_id', 'users.id')
            ->leftJoin('users as gestionnaire', 'ligne_paiement.user_id', 'gestionnaire.id')
            ->leftJoin('pays', 'users.pays_id', 'pays.id')
            ->leftJoin('ville', 'users.ville_id', 'ville.id')
            ->where('ligne_paiement.paiement_id', $idPaiement)
            ->get();
    }

    public static function liste($paiement_id = null, $mode_paiement_id = null, $reference = null, $statut = 1)
    {
        return LignePaiement::orderBy('date_paiement')
            ->when($paiement_id, function ($query) use ($paiement_id) {
                $query->where('paiement_id', $paiement_id);
            })
            ->when($mode_paiement_id, function ($query) use ($mode_paiement_id) {
                $query->where('mode_paiement_id', $mode_paiement_id);
            })
            ->when($reference, function ($query) use ($reference) {
                $query->where('reference', $reference);
            })
            ->where('statut', $statut)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new LignePaiement($arr);
        if ($obj->save()) return $obj;
        else return new LignePaiement();
    }

    public static function supprimer($id)
    {
        $obj = LignePaiement::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function modePaiement()
    {
        return $this->belongsTo(ModePaiement::class);
    }

    public function paiement()
    {
        return $this->belongsTo(Paiement::class, 'paiement_id');
    }
}
