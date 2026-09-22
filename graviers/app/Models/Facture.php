<?php

namespace App\Models;

use App\Models\Paiement;
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
        // Part de TVA sur le transport portée par cette facture.
        'tva_transport_applique',
        // Part d'AIRSI portée par cette facture (10/09/2026).
        'airsi_applique',
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

        // Facture d'avoir (lot 92, 16/09/2026).
        'type_document',
        'facture_origine_id',
        'motif_avoir',
        'lignes_avoir',
        // Courriel de la facture certifiée (lot 93).
        'courriel_envoye_le',
        // Données de la page de vérification de la DGI (lot 96).
        'fne_verification_payload',
    ];

    public const TYPE_FACTURE = 'FACTURE';
    public const TYPE_AVOIR   = 'AVOIR';

    protected $casts = [
        'fne_warning' => 'boolean',
        'fne_certified_at' => 'datetime',
        'fne_request_payload' => 'array',
        'fne_response_payload' => 'array',
        'lignes_avoir' => 'array',
        'fne_verification_payload' => 'array',
        'date_echeance' => 'date',
    ];

    /**
     * L'ÉCRITURE COMPTABLE NAÎT AVEC LA CERTIFICATION (lot 117, 21/09/2026).
     *
     * Posé sur le modèle et non dans les contrôleurs : une facture devient
     * certifiée à plusieurs endroits (validation d'une vente, d'une location,
     * d'un transport, émission d'un avoir). JAMAIS bloquant — une écriture qui
     * ne se produit pas ne doit pas faire échouer une certification déjà
     * acquise auprès de la DGI ; la commande comptabilite:produire-ecritures
     * rattrape ce qui manque.
     */
    protected static function booted(): void
    {
        static::saved(function (Facture $facture) {
            if ($facture->fne_status !== 'certified' || !($facture->wasRecentlyCreated || $facture->wasChanged('fne_status'))) {
                return;
            }
            try {
                \App\Services\Comptabilite\MoteurEcritures::produirePourFacture($facture);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Écriture comptable non produite pour la facture ' . $facture->id . ' : ' . $e->getMessage());
            }
        });
    }

    /** Une facture d'avoir : montant négatif, jamais réclamée au client (lot 92). */
    public function estUnAvoir(): bool
    {
        return ($this->type_document ?? self::TYPE_FACTURE) === self::TYPE_AVOIR;
    }

    /** Les colonnes du lot 92 sont-elles posées (migration 2026_09_16_100000) ? */
    public static function avoirsDisponibles(): bool
    {
        static $ok = null;

        return $ok ??= \Illuminate\Support\Facades\Schema::hasColumn('facture', 'facture_origine_id');
    }

    /** La facture sur laquelle porte cet avoir. */
    public function origine()
    {
        return $this->belongsTo(Facture::class, 'facture_origine_id');
    }

    /** Les avoirs établis sur cette facture. */
    public function avoirs()
    {
        return $this->hasMany(Facture::class, 'facture_origine_id');
    }

    /**
     * LES ARTICLES TELS QUE LA DGI LES A CERTIFIÉS, avec leur identifiant FNE
     * (c'est lui que l'API d'avoir attend) et la quantité qu'il reste possible
     * de créditer, les avoirs déjà établis déduits.
     */
    public function articlesCertifies(): array
    {
        $items = $this->fne_response_payload['invoice']['items'] ?? [];
        $deja = [];
        // Sans la migration du lot 92, la relation ferait une requête sur une
        // colonne absente : page blanche. On lit les articles sans les avoirs.
        $avoirs = self::avoirsDisponibles() ? $this->avoirs : collect();
        foreach ($avoirs as $avoir) {
            foreach ((array) ($avoir->lignes_avoir ?? []) as $ligne) {
                $id = (string) ($ligne['id'] ?? '');
                $deja[$id] = ($deja[$id] ?? 0) + (float) ($ligne['quantity'] ?? 0);
            }
        }
        $articles = [];
        foreach ((array) $items as $item) {
            if (empty($item['id'])) {
                continue;
            }
            $quantite = (float) ($item['quantity'] ?? 0);
            $articles[] = [
                'id'              => (string) $item['id'],
                'reference'       => (string) ($item['reference'] ?? ''),
                'description'     => (string) ($item['description'] ?? ''),
                'quantity'        => $quantite,
                'amount'          => (float) ($item['amount'] ?? 0),
                'measurementUnit' => (string) ($item['measurementUnit'] ?? ''),
                'reste'           => max(0.0, $quantite - ($deja[(string) $item['id']] ?? 0)),
            ];
        }

        return $articles;
    }

    function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    function commande(){
        return $this->belongsTo(Commande::class, 'service_id');
    }

    // Facture d'une location (service = LOCATION, service_id = location.id).
    /**
     * La demande de livraison d'une facture de TRANSPORT.
     *
     * `service_id` désigne l'affaire, quelle qu'elle soit : c'est `service` qui
     * dit laquelle. Appeler cette relation sur une facture de vente renverrait
     * la demande qui porte par hasard le même identifiant — d'où le garde-fou.
     */
    function demandeLivraison(){
        if (strtoupper((string) $this->service) !== strtoupper(\Help::$LIVRAISON)) {
            return $this->belongsTo(DemandeLivraison::class, 'service_id')->whereRaw('1 = 0');
        }

        return $this->belongsTo(DemandeLivraison::class, 'service_id');
    }

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
        // Un avoir ne se réclame pas : il crédite le client (lot 92).
        if ($this->estUnAvoir()) {
            return 0.0;
        }

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
        // TOUS LES CHEMINS, PAS SEULEMENT `facture_id`.
        //
        // La relation `paiements` ne connaît que les règlements portant
        // `facture_id`. Les guichets rattachent le leur à l'AFFAIRE : cette
        // méthode les ignorait, et tous les états de créance annonçaient dues
        // des factures encaissées au comptoir.
        //
        // Le préchargement n'est plus une raison de se tromper : mieux vaut une
        // requête de plus qu'un montant faux.
        return $this->montantDejaRegle();
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
    /**
     * CE QUI A DÉJÀ ÉTÉ RÉGLÉ SUR CETTE FACTURE — PAR TOUS LES CHEMINS.
     *
     * L'argent arrive par deux routes qui ne se voient pas :
     *
     *   · LES GUICHETS (ventes, locations, demandes de livraison) rattachent le
     *     règlement à l'AFFAIRE — `service` + `service_id` — et ne renseignent
     *     PAS `facture_id` ;
     *   · l'écran client à terme rattache le règlement à la FACTURE.
     *
     * Le contrôle « le montant dépasse-t-il le reste à payer ? » ne comptait que
     * la seconde route. Une facture déjà encaissée au guichet lui apparaissait
     * donc ENTIÈREMENT DUE, et il laissait la régler une seconde fois.
     *
     * Constaté le 02/09/2026 sur DA1 TECHNOLOGIE : la facture de location 340101
     * (29 960) et celle de transport 800860 (4 000) réglées deux fois chacune —
     * 33 960 F affichés « Versé en trop » sur l'espace client.
     *
     * On additionne donc les DEUX rattachements, sans compter deux fois un même
     * règlement qui porterait les deux.
     *
     * UNE COMMANDE PORTE PLUSIEURS FACTURES — ET C'EST LÀ QUE ÇA SE GÂTAIT.
     *
     * La facturation émet une facture par groupe d'enlèvements : plusieurs
     * factures partagent donc le MÊME `service_id`. La condition « ou bien le
     * règlement désigne mon affaire » les faisait alors se voler mutuellement
     * leurs encaissements. Reproduit le 04/09/2026 : une commande, deux
     * factures de 100 000 réglées chacune de 100 000 — 200 000 réellement
     * encaissés, 400 000 comptés. Et avec un seul règlement de comptoir de
     * 100 000, les deux factures s'affichaient soldées alors que 100 000
     * restaient dus : la dette disparaissait de la balance âgée.
     *
     * D'où les deux règles ci-dessous :
     *
     *   · un règlement qui DÉSIGNE une facture n'appartient qu'à elle ;
     *   · un règlement de l'affaire, qui n'en désigne aucune, se RÉPARTIT
     *     entre les factures de cette affaire — de la plus ancienne à la plus
     *     récente, chacune absorbant ce qui lui reste dû et pas un franc de
     *     plus. Le surplus éventuel n'est attribué à personne : c'est un
     *     versement en trop, qui se traite ailleurs.
     */
    public function montantDejaRegle(): float
    {
        // 1. Ce qui désigne CETTE facture, et elle seule.
        $propres = $this->reglementsDesignant($this->id);

        if (!$this->service || !$this->service_id) {
            return $propres;
        }

        // 2. Ce qui désigne l'AFFAIRE sans nommer de facture.
        $pot = (float) Paiement::where('statut', \Help::$STATUT_ACTIF)
            ->whereNull('facture_id')
            ->where('service', $this->service)
            ->where('service_id', $this->service_id)
            ->sum('montant_total');

        if ($pot <= 0) {
            return $propres;
        }

        $soeurs = static::where('service', $this->service)
            ->where('service_id', $this->service_id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Les règlements nominatifs de toutes les sœurs en UNE requête : sans
        // cela, un écran qui liste les créances en relance une par facture.
        $nominatifs = Paiement::where('statut', \Help::$STATUT_ACTIF)
            ->whereIn('facture_id', $soeurs->pluck('id'))
            ->selectRaw('facture_id, SUM(montant_total) AS total')
            ->groupBy('facture_id')
            ->pluck('total', 'facture_id');

        $part = 0.0;

        foreach ($soeurs as $soeur) {
            if ($pot <= 0) {
                break;
            }

            $dejaNomme = (float) ($nominatifs[$soeur->id] ?? 0);
            $absorbe = min($pot, max(0, $soeur->totalAPayer() - $dejaNomme));
            $pot -= $absorbe;

            if ((int) $soeur->id === (int) $this->id) {
                $part = $absorbe;
                break;
            }
        }

        return $propres + $part;
    }

    /** Les règlements valides qui nomment cette facture. */
    private function reglementsDesignant($factureId): float
    {
        return (float) Paiement::where('statut', \Help::$STATUT_ACTIF)
            ->where('facture_id', $factureId)
            ->sum('montant_total');
    }

    /** Ce qu'il reste à encaisser, au franc — le FCFA n'a pas de centimes. */
    public function resteAEncaisser(): float
    {
        return \Help::arrondiFranc(max(0, (float) $this->montant - $this->montantDejaRegle()));
    }

}
