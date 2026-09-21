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

use App\Models\Concerns\TraceLesValidations;

class Paiement extends Model
{
    use \App\Traits\CircuitPreuveReglement;
    use HasFactory, SoftDeletes, TraceLesValidations;
    protected $table = 'paiement';
    protected $fillable = [
        // Sans cette entree, l'attribution des points serait ecrite
        // par un update() qui la laisserait TOMBER en silence.
        'points_attribues',
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
        // Date d'envoi du reçu au client (courriel) : un seul envoi par règlement.
        'recu_envoye_le',
        // Double validation (cf. trait DoubleValidationPaiement)
        'user_valide_id',
        'user_valide2_id',
        'date_validation_1',
        'date_validation_2',
        // Circuit après la 2e validation (point 20, 09/09/2026) : « À payer »,
        // preuve jointe, « Effectuée ». Suivi seulement : l'encaissement compte
        // dès la 2e validation.
        'etat_reglement',
        'preuve_paiement',
        'date_preuve',
        'user_preuve_id',
        'date_effectuee',
        'user_effectuee_id',
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

    /**
     * Ce que les filleuls ont réellement payé, par client et par apporteur.
     *
     * LE FILTRE DE DATES ÉTAIT COMMENTÉ. Le contrôleur calculait `$du` et `$au`,
     * les passait ici… où la ligne qui s'en servait était mise en commentaire.
     * L'écran affichait donc tout l'historique, quelle que soit la période
     * demandée — et sa borne haute portait « 29:59:59 », une heure qui n'existe
     * pas et que le serveur aurait refusée le jour où on l'aurait réactivée.
     *
     * LES PAIEMENTS NON RÉGLÉS ÉTAIENT ADMIS. La condition retenait un paiement
     * validé OU dont `montant_total = montant_restant`. Or `montant_restant`
     * tombe à zéro quand le paiement est soldé : ce second terme désignait donc
     * exactement les paiements sur lesquels RIEN n'a été versé. Un état de ce
     * qui a été payé ne peut pas compter ce qui ne l'a pas été.
     *
     * ON GROUPE SUR LES IDENTIFIANTS, jamais sur les libellés : deux clients
     * homonymes joignables au même numéro fusionnaient en une seule ligne.
     *
     * Le GROUP BY ne porte que sur des colonnes nues : une expression y est
     * acceptée par MySQL 8.0.30 — celui du poste de développement — et refusée
     * par le serveur (1055).
     */
    public static function statPaiementFilleule($du = null, $au = null)
    {
        return Paiement::selectRaw("
        SUM(paiement.montant_total) AS total,
        client.id AS clientId,
        CONCAT(" . \App\Models\Client::sqlNomAffiche() . ", ' - ', client.contact1) AS client,
        apporteur.id AS apporteurId,
        apporteur.code AS codeApporteur,
        CONCAT(users.nom_prenoms, ' - ', users.contact) AS apporteur")
            ->join('client', 'client.id', '=', 'paiement.client_id')
            ->join('apporteur', 'apporteur.id', '=', 'client.parrain_id')
            ->join('users', 'users.id', '=', 'apporteur.user_id')
            ->where('paiement.statut', Help::$STATUT_ACTIF)
            ->when($du, function ($query) use ($du) {
                $query->where('paiement.created_at', '>=', $du . ' 00:00:00');
            })
            ->when($au, function ($query) use ($au) {
                $query->where('paiement.created_at', '<=', $au . ' 23:59:59');
            })
            ->groupBy(
                'client.id',
                'client.nom',
                'client.prenom',
                'client.type_client',
                'client.contact1',
                'apporteur.id',
                'apporteur.code',
                'users.nom_prenoms',
                'users.contact'
            )
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

    /**
     * LA FACTURE REGLEE PAR CE PAIEMENT.
     *
     * `facture_id` etait renseigne depuis longtemps, mais aucune relation ne
     * permettait de le suivre. C'est ce chainon qui manquait pour retrouver
     * l'affaire d'une commission quand le paiement lui-meme n'a pas de
     * `service_id` : la facture, elle, le porte souvent.
     */
    public function facture()
    {
        return $this->belongsTo(Facture::class, 'facture_id', 'id');
    }


}
