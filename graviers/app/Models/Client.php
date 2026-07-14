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
        'statut',
        'parrain_id',
        'point',
        'client_a_terme',
        'applique_tva',
        'rccm_clt',
        'ncc_clt',
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
        $localCandidates = [
            public_path('storage/' . $path),
            \Storage::disk('public')->path('temp_pdfs/' . $basename),
            public_path('storage/temp_pdfs/' . $basename),
            \Storage::disk('public')->path('lesBons/' . $basename),
            public_path('storage/lesBons/' . $basename),
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
     * Taux de TVA applicable, retourné en décimal (ex. 0.18 pour 18%).
     *
     * Règle métier : la TVA s'applique à TOUTES les factures, sans exception.
     * Le champ historique `applique_tva` du client est désormais ignoré (il n'est
     * plus la source de vérité). Le taux vient exclusivement de la configuration
     * (Configuration.tva) avec un fallback à 18%.
     */
    public static function tva(?Client $client){
        $conf = Configuration::first();
        $taux = $conf?->tva;
        if ($taux === null || $taux === '') {
            $taux = 18;
        }
        return ((float) $taux) / 100;
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
}
