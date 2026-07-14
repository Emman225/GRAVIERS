<?php

namespace App\Models;

use Help;
use App\Models\Devis;
use App\Models\LignePaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Location;

class Paiement extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'paiement';
    protected $fillable = [
        'client_id',
        'devis_id',
        'code',
        'libelle',
        'montant_total',
        'montant_restant',
        'statut',
        'service_id',
        'service',
        'donnees_service',
        'facture_id',
        'agence_id',
        'caissier_id',
        'numero_recu',
        // Double validation (cf. trait DoubleValidationPaiement)
        'user_valide_id',
        'user_valide2_id',
        'date_validation_1',
        'date_validation_2',
    ];

    protected $casts = [
        // Brouillon de location (créée seulement après confirmation du paiement en ligne).
        'donnees_service' => 'array',
    ];

    public function agence()
    {
        return $this->belongsTo(\App\Models\Agence::class, 'agence_id');
    }

    public function caissier()
    {
        return $this->belongsTo(\App\Models\User::class, 'caissier_id');
    }

    public static function lire($id)
    {
        $obj = Paiement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Paiement();
    }

    public static function lireCode($code)
    {
        $obj = Paiement::where('code', $code)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Paiement();
    }

    public static function lireSurFacture($facture_id)
    {
        $obj = Paiement::where('facture_id', $facture_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Paiement();
    }

    public static function statPaiementFilleule($du, $au)
    {
        return Paiement::selectRaw("
        SUM(paiement.montant_total) AS total,
        CONCAT(client.nom, ' ', client.prenom, ' - ', client.contact1) AS client,
        apporteur.code AS codeApporteur,
        CONCAT(users.nom_prenoms, ' - ', users.contact) AS apporteur")
            ->join('client', 'client.id', '=', 'paiement.client_id')
            ->join('apporteur', 'apporteur.id', '=', 'client.parrain_id')
            ->join('users', 'users.id', '=', 'apporteur.user_id')
            ->where(function ($query) {
                $query->where('paiement.statut', 1)
                    ->orWhereColumn('paiement.montant_total', 'paiement.montant_restant');
            })
            // ->whereBetween('paiement.created_at', [$du." 00:00:00", $au." 29:59:59"])
            ->groupByRaw("
        CONCAT(client.nom, ' ', client.prenom, ' - ', client.contact1),
        apporteur.code,
        CONCAT(users.nom_prenoms, ' - ', users.contact)")
            ->get();
    }

    public static function liste($devis_id = null, $client_id = null)
    {
        return Paiement::orderBy('created_at', 'desc')
            ->when($devis_id, function ($query) use ($devis_id) {
                $query->where('devis_id', $devis_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('client_id', $client_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Paiement($arr);
        if ($obj->save()) return $obj;
        else return new Paiement();
    }

    public static function supprimer($id)
    {
        $obj = Paiement::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function devis()
    {
        return $this->belongsTo(Devis::class, 'devis_id', 'id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The roles that belong to the Paiement
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function modePaiement(): BelongsToMany
    {
        return $this->belongsToMany(modePaiement::class, 'ligne_paiement', 'paiement_id', 'mode_paiement_id')
            ->withPivot(['reference', 'date_paiement']);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id','id')->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }

    public function lignePaiements()
    {
        return $this->hasMany(LignePaiement::class, 'paiement_id');
    }
    public function ligne()
    {
        return $this->hasOne(LignePaiement::class, 'paiement_id', 'id');
    }
    public function commande()
    {
        return $this->belongsTo(Commande::class, 'service_id', 'id');
    }


}
