<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Facture extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'facture';
    protected $fillable = [
        'numero',
        'numero_fne',
        'user_id',
        'montant',
        // Part de la remise et du coût de livraison de la commande imputée à CETTE
        // facture. Une commande peut en générer plusieurs, une par groupe
        // d'enlèvements : la répartition se fait au prorata du HT facturé.
        'remise_appliquee',
        'cout_livraison_applique',
        'statut',
        'service',
        'service_id',
        'client_id',
        'date_echeance',
        'observations',
        'statut_creance',

        // Champs renseignés après certification FNE par la DGI
        'fne_invoice_id',
        'fne_reference',
        'fne_token',
        'fne_balance_sticker',
        'fne_warning',
        'fne_template',
        'fne_payment_method',
        'fne_status',
        'fne_certified_at',
        'fne_error_message',
        'fne_request_payload',
        'fne_response_payload',
    ];

    protected $casts = [
        'fne_warning' => 'boolean',
        'fne_certified_at' => 'datetime',
        'fne_request_payload' => 'array',
        'fne_response_payload' => 'array',
        'date_echeance' => 'date',
    ];

    function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    function commande(){
        return $this->belongsTo(Commande::class, 'service_id');
    }

    // Facture d'une location (service = LOCATION, service_id = location.id).
    function location(){
        return $this->belongsTo(Location::class, 'service_id');
    }

    // Relation manquante : utilisée par facturesNonValidees/facturesValidees
    // (with(['client',...])) et par la vue ($facture->client->nom). Sans elle,
    // l'eager-load lève RelationNotFoundException -> 500 dès qu'il y a des factures.
    function client(){
        return $this->belongsTo(Client::class, 'client_id');
    }

    function paiements(){
        return $this->hasMany(Paiement::class, 'facture_id');
    }

    /**
     * Indique si la facture a été certifiée avec succès par la plateforme FNE.
     */
    public function isCertifiedFne(): bool
    {
        return $this->fne_status === 'certified' && !empty($this->fne_reference);
    }

    public function relances()
    {
        return $this->hasMany(RelanceClientTerme::class, 'facture_id');
    }

    /** La facture porte-t-elle une location plutôt qu'une commande ? */
    public function estUneLocation(): bool
    {
        return $this->service === \Help::$LOCATION;
    }

    /**
     * CE QUE LE CLIENT DOIT SUR CETTE FACTURE — définition unique.
     *
     * Trois écrans en donnaient trois versions : « Créances / Factures » lisait
     * `facture.montant`, l'état « Client à terme » et la balance âgée le
     * recalculaient depuis TOUTE la commande, et `statutCreance()` comparait à
     * une troisième valeur. Une même ligne pouvait donc afficher « Soldée » avec
     * un solde non nul.
     *
     * C'est `facture.montant` qui fait foi. Il est écrit à l'émission comme
     * HT SERVI + TVA + livraison − remise : une commande livrée partiellement,
     * ou facturée en plusieurs fois, produit une facture bien inférieure au
     * total de la commande. Recalculer sur la commande entière gonflait la
     * créance et rendait la dette insoldable — le client payait sa facture et
     * un reste subsistait quoi qu'il fasse.
     *
     * Le repli ne sert qu'aux factures sans montant enregistré, une anomalie :
     * on reconstitue alors depuis la commande, avec la remise et les frais
     * RÉELLEMENT imputés à cette facture-là.
     */
    public function totalAPayer(): float
    {
        $montant = (float) $this->montant;

        if ($montant > 0) {
            return $montant;
        }

        // Une facture de location ne se reconstitue pas depuis une commande :
        // `service_id` y désigne une location, et la relation commande() irait
        // chercher la commande PORTANT CE MÊME NUMÉRO — une autre affaire, dont
        // le montant n'a rien à voir.
        if ($this->estUneLocation()) {
            return 0.0;
        }

        $commande = $this->commande;

        if (!$commande) {
            return 0.0;
        }

        $tauxTva = (float) (Configuration::first()?->tva ?? 18);

        $frais = (float) ($this->cout_livraison_applique ?? $commande->cout_livraison_client ?? 0);
        $remise = (float) ($this->remise_appliquee ?? $commande->remise ?? 0);

        return $commande->montantHT() * (1 + $tauxTva / 100) + $frais - $remise;
    }

    public function montantPaye(): float
    {
        // La relation est presque toujours préchargée par les états de créance :
        // la relire ligne à ligne y ajoutait une requête par facture.
        if ($this->relationLoaded('paiements')) {
            return (float) $this->paiements->where('statut', 1)->sum('montant_total');
        }

        return (float) Paiement::where('facture_id', $this->id)->where('statut', 1)->sum('montant_total');
    }

    public function resteAPayer(): float
    {
        return max(0, $this->totalAPayer() - $this->montantPaye());
    }

    /**
     * DATE D'ÉCHÉANCE DE LA FACTURE.
     *
     * La colonne `facture.date_echeance` n'est écrite NULLE PART : aucun écran,
     * aucun formulaire, aucun service ne la renseigne. Elle est donc vide sur
     * toutes les factures, et tout ce qui en dépend s'écroulait en silence —
     * la balance âgée rangeait la totalité des créances dans la première
     * tranche, aucune facture n'apparaissait jamais en retard, et l'état
     * n'apprenait rien à personne.
     *
     * L'échéance se déduit du délai de paiement accordé au client, exactement
     * comme le fait déjà l'écran des dettes fournisseurs. La colonne reste
     * prioritaire : le jour où elle sera saisie, c'est elle qui s'appliquera.
     */
    public function echeance(): ?\Carbon\Carbon
    {
        if ($this->date_echeance) {
            return \Carbon\Carbon::parse($this->date_echeance)->startOfDay();
        }

        $delai = (int) ($this->client?->delai_paiement ?? 0);

        if ($delai <= 0 || !$this->created_at) {
            return null;
        }

        return \Carbon\Carbon::parse($this->created_at)->startOfDay()->addDays($delai);
    }

    /** L'échéance est-elle passée ? */
    public function estEchue(): bool
    {
        $echeance = $this->echeance();

        return $echeance !== null && \Carbon\Carbon::today()->greaterThan($echeance);
    }

    public function joursRetard(): int
    {
        $echeance = $this->echeance();

        if ($echeance === null) {
            return 0;
        }

        $today = \Carbon\Carbon::today();

        return $today->greaterThan($echeance) ? $echeance->diffInDays($today) : 0;
    }

    public function statutCreance(): string
    {
        if (!empty($this->statut_creance)) {
            return $this->statut_creance;
        }
        $paye  = $this->montantPaye();
        $total = $this->totalAPayer();
        if ($total > 0 && $paye >= $total) {
            return 'Soldée';
        }
        $echeance = $this->echeance();
        if ($echeance === null) {
            return $paye > 0 ? 'Échue partielle' : 'À échoir';
        }
        if (\Carbon\Carbon::today()->lessThanOrEqualTo($echeance)) {
            return 'À échoir';
        }
        return $paye > 0 ? 'Échue partielle' : 'Échue impayée';
    }
}
