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
        // TVA sur le transport, figée à la location (Help::tvaSurTransport).
        'tva_transport',
        // AIRSI figé sur l'affaire (10/09/2026), comme la TVA du transport.
        'airsi',
        // Numéro de bon de commande interne de l'entreprise (09/09/2026),
        // reporté devant chaque désignation, comme sur une vente.
        'numero_bon_commande',
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
                // Le meme generateur que le mobile : un seul format de numero.
                'numero'                => $l['numero'] ?? \Help::genererNumeroUnique('location'),
                'client_id'             => $l['client_id'] ?? null,
                'mode_paiement_id'      => $l['mode_paiement_id'] ?? null,
                'adresse_livraison_id'  => $l['adresse_livraison_id'] ?? null,
                'montant_total'         => $l['montant_total'] ?? 0,
                'etat_location'         => \Help::$LOCATION_EN_ATTENTE,
                'cout_livraison_client' => $l['cout_livraison_client'] ?? 0,
                'tva_transport'         => $l['tva_transport'] ?? 0,
                // AIRSI figé (10/09/2026).
                'airsi'                 => $l['airsi'] ?? 0,
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
        // LE HT SE LIT DANS LES LIGNES, JAMAIS DANS montant_total.
        //
        // Cette colonne n'a PAS le meme sens selon le canal : le site y ecrit le
        // HT, l'application mobile le NET final, TVA et livraison comprises. Y
        // rajouter la TVA et la livraison donnait donc, pour une location
        // mobile, un du gonfle de ces deux montants : la location 382108, due a
        // 55 920, en reclamait 67 840, et son recu annoncait « reste a payer
        // 11 920 » alors qu'elle etait soldee au franc pres.
        //
        // Les lignes, elles, ont un sens unique — detail_location.prix porte le
        // TOTAL de la ligne, quantite et jours compris — et c'est deja la source
        // qu'emploient la facture et son gabarit. Les trois disent desormais la
        // meme chose.
        $ht = (float) $this->detailLocation->sum('prix');

        // Repli defensif : une location sans ligne ne devrait pas exister, mais
        // rendre zero la ferait passer pour soldee.
        if ($ht <= 0) {
            $ht = (float) $this->montant_total;
        }

        return max(0, $ht - (float) ($this->remise ?? 0))
            + (float) ($this->tvaLocation->montant ?? 0)
            + (float) ($this->cout_livraison_client ?? 0)
            + (float) ($this->tva_transport ?? 0)
            // AIRSI figé sur la location (10/09/2026).
            + (float) ($this->airsi ?? 0);
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


    /**
     * L'ÉTAT DE PAIEMENT, TEL QU'IL SE LIT SUR L'ARGENT.
     *
     * `statut` (2 = en cours, 3 = soldé) est une valeur DÉRIVÉE rangée une
     * seconde fois : chaque chemin de paiement — guichet, en ligne — doit
     * penser à la mettre à jour, et celui qui oublie laisse la location
     * marquée « Aucun » pour toujours. C'est ce qui est arrivé aux locations
     * encaissées avant que le guichet ne pose ce drapeau : l'argent était en
     * caisse, la liste disait le contraire.
     *
     * On lit donc d'abord ce qui a été encaissé. Le drapeau est consulté en
     * second : il ne peut qu'AJOUTER une information, jamais en retirer — une
     * location qu'il dit soldée le reste.
     *
     * @return string 'SOLDE', 'PARTIEL' ou 'AUCUN'
     */
    public function etatPaiement(): string
    {
        $du = $this->montantAPayer();
        $paye = $this->montantPayeComptant();

        if (((int) $this->statut) === 3 || ($du > 0 && $paye >= $du)) {
            return 'SOLDE';
        }

        if (((int) $this->statut) === 2 || $paye > 0) {
            return 'PARTIEL';
        }

        return 'AUCUN';
    }


    /**
     * La location est-elle entierement reglee ?
     *
     * A poser partout ou l'on testait `statut == 3` : le bouton « Paiement »,
     * la validation, la facture FNE. Le drapeau seul laissait un bouton
     * « Paiement » sur une location deja payee, et refusait la validation
     * d'une location dont l'argent etait pourtant en caisse.
     */
    public function estSoldee(): bool
    {
        return $this->etatPaiement() === 'SOLDE';
    }

    /** Le même état, dans les mots du gestionnaire. */
    public function libelleEtatPaiement(): string
    {
        return match ($this->etatPaiement()) {
            'SOLDE' => 'Soldé',
            'PARTIEL' => 'En cours',
            default => 'Aucun',
        };
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

    /**
     * PUIS-JE ENCAISSER CETTE AFFAIRE AU GUICHET ?
     *
     * Ce n'est PAS la même question que « le mode de règlement est-il un mode
     * d'agence ? ». Le guichet ne s'écartait que devant la passerelle : une
     * affaire qu'elle a réellement encaissée n'a rien à faire à la caisse.
     *
     * Mais une initiation ÉCHOUÉE — le site crée alors l'affaire tout de même —
     * ou un client qui abandonne la page de paiement laissent une affaire due
     * portant un mode en ligne. Elle n'était encaissable NULLE PART : ni par la
     * passerelle, qui n'a rien pris, ni au guichet, qui l'ignorait. Le caissier
     * ouvrait un sélecteur vide devant un client venu payer. Constaté le
     * 01/09/2026.
     *
     * Les deux questions ont donc chacune leur méthode : confondre les deux
     * bloquait le traitement d'affaires que rien n'avait à bloquer.
     *
     * Aucun risque de double encaissement : `montantEncaissable()` retire déjà
     * ce qui a été perçu et ce qui attend sa seconde validation.
     */
    /** Une location annulée n'existe plus : elle ne s'encaisse pas. */
    public function affaireVivante(): bool
    {
        return $this->etat_location !== \Help::$AFFAIRE_ANNULEE;
    }

    public function encaissableAuGuichet(): bool
    {
        // L'ÉTAT D'ABORD. Cette règle ne parlait que du mode de règlement : une
        // affaire annulée passait donc le contrôle, du moment que rien n'avait
        // encore été encaissé dessus. Le défaut a été constaté sur le guichet
        // des ventes ; il était identique ici, faute de location annulée dans
        // le jeu d'essai pour le révéler.
        return $this->affaireVivante()
            && ($this->reglementEnAgence() || $this->montantPayeComptant() <= 0);
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

    /** La pièce jointe du bon de commande interne (09/09/2026), comme Commande::blClient. */
    public function blClient(){
        return $this->hasOne(BlClient::class, 'location_id', 'id');
    }

    public function adresseLivraison(){
        return $this->belongsTo(AdresseLivraison::class);
    }

    public function livraisons(){
        return $this->hasManyThrough(DetailLocation::class, Livraison::class);
    }

    /**
     * LES COURSES DE LA LOCATION (10/09/2026).
     *
     * Une course de location est une ligne de `livraison` dont `provenance`
     * vaut LOCATION et dont `detail_commande_id` porte l'identifiant d'une
     * LIGNE de la location (detail_location) — c'est ainsi que la validation
     * la crée (UserController, traitement de la location), et c'est ce que
     * lit lignesALivrerDeNouveau(). PAS `detail_livraison_id` : cette colonne
     * n'est renseignée que pour les demandes de livraison. Une première
     * version la lisait, et le client ne voyait aucun code sur ses locations.
     */
    public function courses()
    {
        $lignes = $this->detailLocation->pluck('id');
        if ($lignes->isEmpty()) {
            return collect();
        }

        return Livraison::where('provenance', \Help::$LOCATION)
            ->whereIn('detail_commande_id', $lignes)
            ->orderBy('id')
            ->get();
    }

    /** Les courses acceptées, celles dont les codes ont un sens pour le client. */
    public function coursesAcceptees()
    {
        return $this->courses()->filter(fn (Livraison $l) => (int) $l->accepte === Livraison::ACCEPTEE)->values();
    }

    /**
     * OÙ EN EST LA LIVRAISON DU MATÉRIEL (10/09/2026).
     *
     * L'état de la location ne dit pas cela : elle est EN COURS de la
     * validation jusqu'au retour du matériel, que le livreur ait livré ou
     * non. Le client, lui, veut savoir si son matériel est arrivé — signalé
     * le 10/09/2026 : « la livraison est terminée par le livreur mais le
     * statut est toujours en cours ».
     *
     * Retourne null sans course, sinon ['code', 'libelle', 'date'] :
     *   LIVREE / RETIREE  toutes les courses acceptées sont livrées ;
     *   EN_LIVRAISON / A_RETIRER  au moins une course acceptée pas encore livrée ;
     *   A_CONFIRMER  la course attend l'acceptation du livreur.
     */
    public function etatLivraison(): ?array
    {
        $courses = $this->courses()->filter(fn (Livraison $l) => (int) $l->accepte !== Livraison::REFUSEE);
        if ($courses->isEmpty()) {
            return null;
        }
        $retrait   = $this->estRetraitSurPlace();
        $acceptees = $courses->filter(fn (Livraison $l) => (int) $l->accepte === Livraison::ACCEPTEE);
        $livrees   = $acceptees->filter(fn (Livraison $l) => $l->etat_livraison === \Help::$LIVRAISON_LIVREE);

        if ($acceptees->isNotEmpty() && $livrees->count() === $acceptees->count()) {
            $date = $livrees->max('updated_at');
            $quand = $date ? ' le ' . \Help::dateHeure($date) : '';
            return $retrait
                ? ['code' => 'RETIREE', 'libelle' => 'Retirée' . $quand, 'date' => $date]
                : ['code' => 'LIVREE', 'libelle' => 'Livrée' . $quand, 'date' => $date];
        }
        if ($acceptees->isNotEmpty()) {
            return $retrait
                ? ['code' => 'A_RETIRER', 'libelle' => 'À retirer chez le fournisseur', 'date' => null]
                : ['code' => 'EN_LIVRAISON', 'libelle' => 'En livraison', 'date' => null];
        }

        return ['code' => 'A_CONFIRMER', 'libelle' => 'Livreur à confirmer', 'date' => null];
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
    /**
     * LE MODE DE RÉCUPÉRATION CHOISI PAR LE CLIENT.
     *
     * `est_livrable` porte ce choix. Mais la colonne est NOT NULL avec 0 pour
     * défaut : une location créée AVANT son ajout se lit « retrait sur place »
     * alors que le client n'a peut-être rien demandé de tel.
     *
     * L'adresse de livraison tranche : on n'en choisit une que pour se faire
     * livrer. Quand elle est là, elle l'emporte sur un `est_livrable` à 0 qui
     * n'est qu'une valeur par défaut.
     *
     * Le gestionnaire ne choisit plus ce mode à la validation — il constate
     * celui du client. La règle vit donc ici, et l'écran comme le contrôleur
     * la lisent au même endroit.
     */
    public function estRetraitSurPlace(): bool
    {
        if ((int) $this->est_livrable === 1) {
            return false;
        }

        return !$this->adresse_livraison_id;
    }

    /** Le libellé à afficher, sans interprétation à faire côté écran. */
    public function libelleModeRecuperation(): string
    {
        return $this->estRetraitSurPlace()
            ? 'Retrait sur place'
            : 'Livraison';
    }

}
