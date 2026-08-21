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

    /**
     * Total net à payer par le client sur cette location.
     *
     * Même formule que celle retenue à la facturation
     * (OrdersController::genererFactureLocation) : HT − remise, puis TVA et
     * livraison. montant_total porte le HT.
     */
    public function montantAPayer(): float
    {
        return max(0, (float) $this->montant_total - (float) ($this->remise ?? 0))
            + (float) ($this->tvaLocation->montant ?? 0)
            + (float) ($this->cout_livraison_client ?? 0);
    }

    /** Ce qui a réellement été encaissé sur la location. */
    public function montantPayeComptant(): float
    {
        return (float) LignePaiement::where('service', \Help::$LOCATION)
            ->where('service_id', $this->id)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->sum('montant');
    }

    /**
     * Reste réellement dû sur la location.
     * Le fcfa n'a pas de décimales : un résidu < 1 est considéré comme nul.
     */
    public function montantRestantDu(): float
    {
        $reste = $this->montantAPayer() - $this->montantPayeComptant();
        return $reste < 1 ? 0.0 : $reste;
    }

    /** Encaissements saisis au guichet mais pas encore validés par un second administrateur. */
    public function montantEnAttenteValidation(): float
    {
        return (float) LignePaiement::where('service', \Help::$LOCATION)
            ->where('service_id', $this->id)
            ->where('statut', 2)
            ->sum('montant');
    }

    /**
     * Ce qu'il reste à ENCAISSER, par opposition à ce qu'il reste à devoir.
     *
     * Les deux notions diffèrent tant qu'un encaissement attend sa seconde
     * validation : il ne solde pas encore la location, mais il ne doit pas non
     * plus pouvoir être saisi une deuxième fois. Sans cette distinction, deux
     * encaissements du montant total pouvaient coexister au guichet et se
     * valider ensuite tous les deux. Même règle que pour les ventes et les
     * demandes de livraison.
     */
    public function montantEncaissable(): float
    {
        $reste = $this->montantAPayer()
            - $this->montantPayeComptant()
            - $this->montantEnAttenteValidation();

        return $reste < 1 ? 0.0 : $reste;
    }

    /**
     * Le règlement se fait-il en agence (donc hors ligne) ?
     * Une location réglée en ligne est encaissée par la passerelle, pas au guichet.
     */
    public function reglementEnAgence(): bool
    {
        return (int) ($this->modeDePaiement?->en_ligne ?? 0) === 0;
    }

    public function modeDePaiement()
    {
        return $this->belongsTo(ModePaiement::class, 'mode_paiement_id');
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

    /**
     * LIGNES DONT LA LIVRAISON A ÉTÉ REFUSÉE ET N'A PAS ÉTÉ RECONFIÉE.
     *
     * La validation d'une location est verrouillée sur l'état EN ATTENTE, et
     * l'affectation fait aussitôt passer la location EN COURS. Quand le livreur
     * refusait, il n'existait donc AUCUN moyen de réaffecter : le matériel
     * n'était jamais livré, et l'écran répondait « Cette location est déjà
     * traitée ».
     *
     * Une ligne est à refaire dès qu'elle porte un refus et plus aucune course
     * active — c'est ce que cette méthode retourne, et c'est sur elle que
     * l'écran rouvre la validation.
     */
    public function lignesALivrerDeNouveau()
    {
        return $this->detailLocation->filter(function ($detail) {
            $courses = \App\Models\Livraison::where('provenance', \Help::$LOCATION)
                ->where('detail_commande_id', $detail->id)
                ->get();

            if ($courses->isEmpty()) {
                return false;
            }

            $refusee = $courses->where('accepte', \App\Models\Livraison::REFUSEE)->isNotEmpty();
            $active  = $courses->where('accepte', '!=', \App\Models\Livraison::REFUSEE)->isNotEmpty();

            return $refusee && !$active;
        })->values();
    }

    /** Une livraison de cette location attend-elle d'être reconfiée ? */
    public function attendUneReaffectation(): bool
    {
        return $this->lignesALivrerDeNouveau()->isNotEmpty();
    }
}
