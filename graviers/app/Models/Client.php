<?php

namespace App\Models;

use Help;
use App\Models\blog;
use App\Models\User;
use App\Models\Apporteur;
use App\Models\Livraison;
use App\Models\Configuration;
use App\Models\DemandeLivraison;
use Illuminate\Database\Eloquent\Model;
use App\Models\DemandeCompteClientATerme;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'client';
    protected $fillable = [
        'user_id',
        'nom',
        'prenom',
        'email',
        'contact1',
        'contact2',
        'code_parrain',
        'rccm_clt',
        'ncc_clt',
        'type_client',
        'nature_fne',
        'statut',
        'parrain_id',
        'point',
        'client_a_terme',
        'applique_tva',
        // TVA sur le transport, retirable par client (10/09/2026) ; appliquée par défaut.
        'applique_tva_transport',
        // Code d'exonération FNE choisi au retrait de la TVA (lot 82, 15/09/2026).
        'code_exoneration_fne',
        'rccm_clt',
        'ncc_clt',
        // SANS CETTE LIGNE, LA VALEUR EST SILENCIEUSEMENT IGNOREE.
        // Le regime d'imposition du client figure sur la facture et la DGI
        // l'attend sur une facture entre entreprises. La colonne existait et
        // FneService la lisait, mais elle n'etait ni saisissable ni
        // enregistrable : la ligne sortait vide sur chaque facture.
        'regime_imposition',
        'dfe',
        'registre_commerce',
        'plafond_credit',
        'delai_paiement',
        'notes',
    ];

    public static function lire($id)
    {
        $obj = Client::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Client();
    }

    /**
     * Le nom sous lequel le client doit apparaître partout.
     *
     * Une entreprise a une RAISON SOCIALE, pas un prénom. Le formulaire recopie
     * pourtant la raison sociale dans les deux champs, si bien qu'un affichage
     * « nom prénom » donnait « TEST TEST ». On ne rend donc que le nom dès que
     * le prénom n'apporte rien : client entreprise, prénom vide, ou prénom
     * identique au nom.
     */
    public function getDisplayNameAttribute(): string
    {
        $nom = trim((string) $this->nom);
        $prenom = trim((string) $this->prenom);
        if ($this->type_client === 'ENTREPRISE' || $prenom === '' || $prenom === $nom) {
            return $nom;
        }
        return trim($nom . ' ' . $prenom);
    }

    /**
     * La MÊME règle, exprimée en SQL.
     *
     * Plusieurs listes composent le nom du client directement dans leur requête,
     * par concaténation : elles ne passent jamais par l'accesseur ci-dessus et
     * continuaient donc d'afficher « TEST TEST ». Plutôt que de corriger chaque
     * requête à sa façon, elles appellent toutes cette expression — les deux
     * définitions restent ainsi côte à côte, et se corrigent ensemble.
     *
     * @param  string  $alias  Nom de la table (ou son alias) dans la requête.
     */
    public static function sqlNomAffiche(string $alias = 'client'): string
    {
        return "TRIM(CASE
                WHEN {$alias}.type_client = 'ENTREPRISE'
                     OR {$alias}.prenom IS NULL
                     OR TRIM({$alias}.prenom) = ''
                     OR TRIM({$alias}.prenom) = TRIM({$alias}.nom)
                THEN {$alias}.nom
                ELSE CONCAT(TRIM({$alias}.nom), ' ', TRIM({$alias}.prenom))
            END)";
    }

    /**
     * Résout un chemin de fichier (relatif au disque public) en chemin absolu.
     *
     * Robuste face aux incohérences de stockage du projet : l'upload des bons
     * écrit dans public/storage/temp_pdfs tandis que le déplacement vers
     * lesBons et la lecture utilisent le disque 'public' (storage/app/public).
     * Quand le lien symbolique public/storage n'est pas un vrai lien, ces
     * dossiers divergent et le fichier reste « bloqué » dans temp_pdfs.
     * On cherche donc le fichier dans TOUS les emplacements plausibles, par
     * nom de fichier, avant d'abandonner — y compris dans apigravier (uploads
     * via l'API mobile).
     */
    public static function resolveStoragePath(?string $path): ?string
    {
        if (!$path) return null;

        $path     = ltrim(str_replace('\\', '/', $path), '/');
        $basename = basename($path);

        // 1) Emplacement nominal : disque 'public' = storage/app/public/<path>
        if (\Storage::disk('public')->exists($path)) {
            return \Storage::disk('public')->path($path);
        }

        // 2) Emplacements de repli locaux (dossier public physique + fichier
        //    resté dans temp_pdfs faute de déplacement vers lesBons).
        // storage/app/public est l'ancien emplacement : c'était la racine du disque
        // 'public' avant qu'elle ne soit déplacée vers public/storage, le seul dossier
        // réellement servi par le web sur cet hébergement. Les bons téléversés avant
        // ce changement — ou par un code qui visait encore ce chemin en dur — y sont
        // restés. Sans ces candidats, ils étaient introuvables alors qu'ils étaient
        // bien sur le serveur : « Le bon de commande est introuvable » pour un fichier
        // parfaitement présent.
        $ancienneRacine = storage_path('app' . DIRECTORY_SEPARATOR . 'public') . DIRECTORY_SEPARATOR;

        $localCandidates = [
            public_path('storage/' . $path),
            \Storage::disk('public')->path('temp_pdfs/' . $basename),
            public_path('storage/temp_pdfs/' . $basename),
            \Storage::disk('public')->path('lesBons/' . $basename),
            public_path('storage/lesBons/' . $basename),
            $ancienneRacine . str_replace('/', DIRECTORY_SEPARATOR, $path),
            $ancienneRacine . 'temp_pdfs' . DIRECTORY_SEPARATOR . $basename,
            $ancienneRacine . 'lesBons' . DIRECTORY_SEPARATOR . $basename,
        ];
        foreach ($localCandidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // 3) Projet API séparé (apigravier/storage/app/public/...).
        $apiBase = dirname(base_path()) . DIRECTORY_SEPARATOR . 'apigravier'
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app'
            . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
        $apiCandidates = [
            $apiBase . str_replace('/', DIRECTORY_SEPARATOR, $path),
            $apiBase . 'temp_pdfs' . DIRECTORY_SEPARATOR . $basename,
            $apiBase . 'lesBons' . DIRECTORY_SEPARATOR . $basename,
        ];
        foreach ($apiCandidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Taux de TVA de la configuration, en décimal (0.18 pour 18 %), 18 % à défaut.
     */
    public static function tauxConfig(): float
    {
        $taux = Configuration::first()?->tva;
        if ($taux === null || $taux === '') {
            $taux = 18;
        }

        return ((float) $taux) / 100;
    }

    /**
     * Taux de TVA applicable à la MARCHANDISE de ce client, en décimal.
     *
     * Règle du 10/09/2026 : la TVA est appliquée à tous les clients par défaut,
     * mais un client donné peut en être dispensé au back-office (applique_tva
     * à 0 : bouton « Retirer la TVA » de la liste des clients). Sans client
     * (visiteur, panier anonyme) : le taux de la configuration.
     */
    /** Les deux exonérations que la DGI distingue (codes de taxe à 0 %). */
    public const EXONERATIONS_FNE = [
        'TVAD' => 'Exonération légale',
        'TVAC' => 'Exonération conventionnelle',
    ];

    /**
     * LE CODE DE TAXE DGI D'UN CLIENT DISPENSÉ DE TVA (lot 82, 15/09/2026) :
     * celui choisi par l'administrateur au retrait de la TVA ; à défaut (client
     * dispensé avant cette évolution), le réglage FNE_EXEMPT_TAX.
     */
    public function codeExonerationFne(): string
    {
        $code = strtoupper(trim((string) ($this->code_exoneration_fne ?? '')));
        if (isset(self::EXONERATIONS_FNE[$code])) {
            return $code;
        }

        return (string) config('fne.defaults.exempt_tax', 'TVAD');
    }

    /** « Exonération légale (TVAD) », pour les écrans. */
    public function libelleExonerationFne(): string
    {
        $code = $this->codeExonerationFne();

        return (self::EXONERATIONS_FNE[$code] ?? 'Exonération') . ' (' . $code . ')';
    }

    public static function tva(?Client $client){
        if ($client && $client->applique_tva !== null && (int) $client->applique_tva === 0) {
            return 0.0;
        }

        return self::tauxConfig();
    }

    /**
     * Taux de TVA applicable au TRANSPORT de ce client, en décimal : nul si la
     * configuration ne taxe pas le transport, ou si ce client en est dispensé
     * (applique_tva_transport à 0) ; sinon le taux de la configuration —
     * indépendamment de la TVA marchandise (10/09/2026).
     */
    public static function tvaTransport(?Client $client): float
    {
        if ((int) (Configuration::first()?->tva_transport ?? 0) !== 1) {
            return 0.0;
        }
        if ($client && $client->applique_tva_transport !== null && (int) $client->applique_tva_transport === 0) {
            return 0.0;
        }

        return self::tauxConfig();
    }

    /**
     * L'AIRSI (acompte d'impôt sur le revenu du secteur informel) s'applique
     * au client qui n'a pas déclaré un régime réel d'imposition — réel normal
     * (RNI) ou réel simplifié (RSI) : particuliers, micro-entreprises,
     * entreprenants, et tout client sans régime renseigné (10/09/2026).
     */
    /** Les gabarits de la DGI pour une organisation (lot 100, 16/09/2026). */
    public const NATURES_FNE = [
        'B2B' => 'Entreprise privée',
        'B2G' => 'Administration ou institution publique',
        'B2F' => 'Client établi à l\'étranger',
    ];

    public static function soumisAirsi(?Client $client): bool
    {
        if (!$client) {
            return false;
        }
        $code = \App\Support\RegimeImposition::code($client->regime_imposition);

        return !in_array($code, ['RNI', 'RSI'], true);
    }


    public function comptes()
    {
        return $this->belongsToMany(Compte::class, 'client_comptes');
    }
    
    public function DemandeCompteClientATerme()
    {
        return $this->hasOne(DemandeCompteClientATerme::class);
    }

    public function notes()
    {
        return $this->belongsToMany(Client::class, 'note_produit')->withPivot('produit_id', 'client_id', 'note', 'avis', 'created_at', 'statut');
    }

    public static function lireSurUser($idUser)
    {
        $obj = Client::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Client();
    }

    public static function liste($code_parrain = null, $type_client = null)
    {
        return Client::orderBy('nom', 'asc')
            ->orderBy('prenom', 'asc')
            ->when($code_parrain, function ($query) use ($code_parrain) {
                $query->where('code_parrain', $code_parrain);
            })
            ->when($type_client, function ($query) use ($type_client) {
                $query->where('type_client', $type_client);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Client($arr);
        if ($obj->save()) return $obj;
        else return new Client();
    }

    public static function supprimer($id)
    {
        $obj = Client::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
    /**
     * Get all of the commande for the Client
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function Commande()
    {
        return $this->hasMany(Commande::class);
    }

    public function produits()
    {
        return $this->belongsToMany(Produit::class, 'likes')
            ->withPivot('id', 'created_at', 'updated_at', 'deleted_at');
    }

    public function Livraisons()
    {
        return $this->hasMany(Livraison::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'']);
    }
    public function apporteur()
    {
        return $this->belongsTo(Apporteur::class, 'code_parrain');
    }

    public function demandesLivraison()
    {
        return $this->hasMany(DemandeLivraison::class);
    }

    public function clientATerme()
    {
        return $this->hasOne(DemandeCompteClientATerme::class);
    }
    public function lignes()
    {
        return $this->hasManyThrough(LignePaiement::class, Paiement::class, 'client_id', 'paiement_id', 'id', 'id');
    }

    public function blogs()
    {
        return $this->belongsToMany(blog::class, 'blog_commentaires')->withPivot('note', 'commentaire', 'created_at', 'updated_at', 'id', 'statut');
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class, 'client_id');
    }

    public function getApporteur()
    {
        return $this->belongsTo(Apporteur::class, 'code_parrain');
    }
    public function factures()
    {
        return $this->hasManyThrough(Facture::class, Commande::class, 'client_id', 'service_id', 'id', 'id');
    }

    public function relances()
    {
        return $this->hasMany(RelanceClientTerme::class, 'client_id');
    }

    /**
     * Encours de crédit : tout ce que le client doit encore sur ses commandes.
     *
     * Somme des restes dus, commandes annulées exclues. On se fonde sur les
     * COMMANDES et non sur les factures : une facture n'est émise qu'après
     * livraison, alors que le crédit est engagé dès la commande passée. S'appuyer
     * sur les factures laisserait un client enchaîner des commandes sans qu'aucune
     * ne compte dans son encours.
     */
    public function encoursCredit(): float
    {
        // Une LOCATION engage le crédit exactement comme une commande : le client
        // dispose du matériel et paiera plus tard. Ne compter que les commandes
        // laissait une location de 260 000 FCFA hors du plafond — et permettait
        // ensuite de commander comme si rien n'était dû.
        $locations = (float) Location::where('client_id', $this->id)
            ->where('etat_location', '!=', 'ANNULEE')
            ->get()
            ->sum(fn (Location $l) => $l->montantRestantDu());

        // Une DEMANDE DE LIVRAISON engage le crédit au même titre : le camion
        // part, le transport est rendu, et le client à terme paiera plus tard.
        // Elle en était absente, si bien que le transport dû n'amputait jamais
        // le plafond — ni pour la demande elle-même, ni pour les achats suivants.
        // Aucun état « annulée » n'existe sur ces demandes : on écarte donc les
        // seules lignes désactivées.
        $livraisons = (float) DemandeLivraison::where('client_id', $this->id)
            ->where('statut', Help::$STATUT_ACTIF)
            ->get()
            ->sum(fn (DemandeLivraison $d) => $d->montantRestantDu());

        return $locations + $livraisons + (float) Commande::where('client_id', $this->id)
            ->whereNotIn('etat_commande', [
                'ANNULEE',
                // Une commande « EN ATTENTE DE PAIEMENT » n'est pas confirmée : le
                // client a lancé un règlement en ligne sans le terminer. Aucune
                // marchandise n'est engagée — elle n'entre même pas dans la file du
                // gestionnaire — donc aucun crédit n'est pris.
                //
                // La compter était doublement faux : elle amputait le plafond d'un
                // montant que le client ne doit pas, et comme elle ne peut jamais
                // être soldée, elle le bloquait DÉFINITIVEMENT. Un panier abandonné
                // sur la page de paiement suffisait à condamner la ligne de crédit.
                Help::$COMMANDE_EN_ATTENTE_PAIEMENT,
            ])
            ->get()
            ->sum(fn (Commande $c) => $c->montantRestantDu());
    }

    /**
     * Crédit encore disponible, jamais négatif.
     *
     * Renvoie null quand aucun plafond n'est défini : on ne peut pas faire
     * respecter une limite qui n'existe pas, et l'appelant doit décider quoi en
     * faire plutôt que de recevoir un 0 trompeur.
     */
    public function plafondDisponible(): ?float
    {
        $plafond = (float) ($this->plafond_credit ?? 0);

        if ($plafond <= 0) {
            return null;
        }

        return max(0, $plafond - $this->encoursCredit());
    }
}
