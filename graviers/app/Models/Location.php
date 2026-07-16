<?php

namespace App\Models;

use App\Models\Client;
use App\Models\Paiement;
use App\Models\TvaCommande;
use App\Models\DetailLocation;

use App\Models\AdresseLivraison;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "location";
    protected $fillable = [
        'numero',
        'client_id',
        'mode_paiement_id',
        'adresse_livraison_id',
        'date_location',
        'montant_total',
        'etat_location',
        'note',
        'remise',
        'cout_livraison_client',
        // 0 = « Retrait sur place » (le client vient chercher) : aucun livreur n'intervient.
        'est_livrable',
        'statut',
        // Cycle de vie (phase c)
        'caution',
        'caution_restituee',
        'caution_retenue',
        'motif_retenue',
        'date_retour',
        'livreur_id',
        'vehicule_id',
    ];

    protected $casts = [
        'caution_restituee' => 'boolean',
        'date_retour'       => 'date',
    ];

    public static function lire($id)
    {
        $obj = Location::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Location();
    }

    public function detailLocation(){
        return $this->hasMany(DetailLocation::class);
    }

    // Paiements de CETTE location. La table `paiement` n'a pas de colonne location_id :
    // le lien se fait via service='LOCATION' + service_id=location.id (comme
    // service='COMMANDE' pour les commandes). NB : PHP est insensible à la casse pour
    // les noms de méthodes, donc $location->paiements résout vers cette méthode.
    // Utilisé par le paiement de location et Help::montantLocationRestant().
    public function paiementS(){
        return $this->hasMany(Paiement::class, 'service_id')
                    ->where('service', \Help::$LOCATION);
    }

    public function client(){
        return $this->belongsTo(Client::class,'client_id')->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }

    // Facture FNE de CETTE location (service='LOCATION', service_id=location.id).
    // Sert à savoir si une facture a déjà été générée (bouton "Générer facture").
    public function factureFne(){
        return $this->hasOne(\App\Models\Facture::class, 'service_id')
                    ->where('service', \Help::$LOCATION);
    }

    /**
     * Crée la location à partir du "brouillon" stocké sur le paiement (donnees_service),
     * APRÈS confirmation du paiement en ligne. Idempotent : si le paiement est déjà lié
     * à une location (service_id renseigné), on renvoie simplement celle-ci.
     * Appelé depuis le callback PaySecure ET depuis l'URL de retour (verifiePaiement).
     */
    /**
     * Crée une location + ses lignes + sa TVA à partir d'un tableau "brouillon"
     * (mêmes clés que le payload construit par enregistrementLocation). Source de
     * vérité unique de la création : utilisée par le paiement en ligne (après
     * confirmation), le fallback (échec init paiement) et les modes hors-ligne.
     */
    public static function creerDepuisDonnees(array $data, int $statut = 1)
    {
        if (empty($data) || empty($data['location'])) return null;

        return \DB::transaction(function () use ($data, $statut) {
            $l = $data['location'];
            $location = self::create([
                'numero'                => $l['numero'] ?? uniqid(),
                'client_id'             => $l['client_id'] ?? null,
                'mode_paiement_id'      => $l['mode_paiement_id'] ?? null,
                'adresse_livraison_id'  => $l['adresse_livraison_id'] ?? null,
                'montant_total'         => $l['montant_total'] ?? 0,
                'etat_location'         => \Help::$LOCATION_EN_ATTENTE,
                'cout_livraison_client' => $l['cout_livraison_client'] ?? 0,
                // Choix de livraison du client, figé dans le brouillon au moment du
                // checkout : le callback de paiement n'a pas de session pour le relire.
                // Repli sur 1 (livrable) = comportement historique, pour les brouillons
                // créés avant l'ajout du champ et encore en attente de callback.
                'est_livrable'          => $l['est_livrable'] ?? 1,
                'remise'                => $l['remise'] ?? 0,
                'statut'                => $statut,
            ]);

            foreach (($data['details'] ?? []) as $d) {
                \App\Models\DetailLocation::create([
                    'produit_id'    => $d['produit_id'] ?? null,
                    'location_id'   => $location->id,
                    'qte'           => $d['qte'] ?? 1,
                    'debut'         => $d['debut'] ?? null,
                    'fin'           => $d['fin'] ?? null,
                    'prix'          => $d['prix'] ?? 0,
                    'nombre_jour'   => $d['nombre_jour'] ?? 1,
                    'etat_location' => \Help::$LOCATION_EN_ATTENTE,
                ]);
            }

            \App\Models\TvaCommande::create([
                'client_id'    => $location->client_id,
                'commande_id'  => $location->id,
                'montant'      => intVal($data['tva'] ?? 0),
                'type_affaire' => \Help::$LOCATION,
            ]);

            return $location;
        });
    }

    /**
     * Crée la location APRÈS confirmation d'un paiement en ligne, à partir du
     * brouillon stocké sur le paiement (donnees_service). Idempotent : si le paiement
     * est déjà lié à une location, on la renvoie sans rien recréer.
     * Appelé depuis le callback PaySecure ET depuis l'URL de retour (verifiePaiement).
     */
    public static function creerDepuisPaiement($paiement)
    {
        if (!$paiement) return null;
        if ($paiement->service_id) {
            return self::find($paiement->service_id);
        }

        $data = $paiement->donnees_service;
        if (is_string($data)) $data = json_decode($data, true);
        if (empty($data)) return null;

        // Paiement confirmé -> soldé (3) si plus rien à payer, sinon à payer (1).
        $statut = ($paiement->montant_restant <= 0) ? 3 : 1;
        $location = self::creerDepuisDonnees($data, $statut);

        if ($location) {
            $paiement->service_id = $location->id;
            $paiement->save();
            \App\Models\LignePaiement::where('paiement_id', $paiement->id)
                ->update(['service_id' => $location->id]);
        }
        return $location;
    }

    public function tvaLocation(){
        // Filtre type_affaire=LOCATION : commande_id d'une location et d'une commande
        // peuvent avoir la même valeur (tables séparées) ; sans ce filtre, on risquait
        // de récupérer la TVA d'une COMMANDE de même id.
        return $this->hasOne(TvaCommande::class, 'commande_id')
                    ->where('type_affaire', \Help::$LOCATION);
    }

    public function adresseLivraison(){
        return $this->belongsTo(AdresseLivraison::class);
    }

    public function livraisons(){
        return $this->hasManyThrough(DetailLocation::class, Livraison::class);
    }

    // Livreur / véhicule affectés à la location (phase c).
    public function livreur(){
        return $this->belongsTo(Livreur::class, 'livreur_id')->withDefault();
    }

    public function vehicule(){
        return $this->belongsTo(Vehicule::class, 'vehicule_id')->withDefault();
    }

    /**
     * Libellé lisible de l'état. etat_location est un enum stocké en clair
     * ('EN ATTENTE'/'EN COURS'/'TERMINE'). Repli si une valeur entière legacy traîne.
     */
    public function etatLibelle(): string
    {
        $map = [1 => 'EN ATTENTE', 2 => 'EN COURS', 3 => 'TERMINE'];
        $v = $this->etat_location;
        if (is_numeric($v) && isset($map[(int) $v])) {
            return $map[(int) $v];
        }
        return (string) ($v ?: 'EN ATTENTE');
    }
}
