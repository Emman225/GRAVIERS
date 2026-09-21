<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Une opération effectuée dans le back-office.
 *
 * L'écriture passe toujours par Audit::log() : c'est le seul point d'entrée,
 * et il ne lève JAMAIS d'exception. Une trace d'audit qui ferait échouer un
 * encaissement serait pire que l'absence de trace.
 */
class Audit extends Model
{
    use HasFactory;

    protected $table = 'audits';

    /** Une trace ne se modifie pas : seul created_at a un sens. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'nom_utilisateur', 'type_user_id',
        'action', 'methode', 'url', 'route_name', 'donnees',
        'adresse_ip', 'user_agent',
    ];

    protected $casts = [
        'donnees'    => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Champs jamais recopiés dans le journal, quelle que soit leur casse.
     *
     * Un audit se relit ; il ne doit pas devenir l'endroit où traînent les
     * mots de passe. Le jeton CSRF et les champs techniques sont écartés au
     * même titre, faute d'intérêt.
     */
    public const CHAMPS_SENSIBLES = [
        'password', 'password_confirmation', 'motdepasse', 'mot_de_passe',
        'ancien_mot_de_passe', 'nouveau_mot_de_passe', 'confirmation',
        'current_password', 'new_password', 'token', '_token', 'api_token',
        'remember_token', 'secret', 'cle', 'cle_api', 'api_key', 'authorization',
        'carte', 'cvv', 'cvc', 'pin', 'code_secret',
    ];

    /**
     * CE QUI S'EST PASSÉ, EN UNE PHRASE.
     *
     * Le journal servait à ceux qui savent lire une route et un JSON. Or il
     * existe précisément pour être relu par quelqu'un qui NE code pas : un
     * gérant qui cherche qui a relevé un plafond, un comptable qui veut savoir
     * qui a annulé une facture.
     *
     * Le libellé porte déjà « Validation — commande » ; on le remet à
     * l'endroit, avec l'auteur et le moment.
     */
    public function recit(): string
    {
        [$verbe, $objet] = $this->verbeEtObjet();

        $qui = trim((string) $this->nom_utilisateur) ?: 'Un utilisateur';

        $quand = $this->created_at
            ? ' le ' . $this->created_at->format('d/m/Y') . ' à ' . $this->created_at->format('H:i')
            : '';

        // Un verbe conjugué se lit mieux qu'une étiquette : « a validé une
        // commande » plutôt que « Validation — commande ».
        $conjugue = [
            'Création'     => 'a créé',
            'Modification' => 'a modifié',
            'Suppression'  => 'a supprimé',
            'Validation'   => 'a validé',
            'Annulation'   => 'a annulé',
            'Encaissement' => 'a encaissé',
            'Règlement'    => 'a réglé',
            'Blocage'      => 'a bloqué',
            'Déblocage'    => 'a débloqué',
        ][$verbe] ?? 'est intervenu sur';

        $article = in_array($verbe, ['Encaissement', 'Règlement'], true) ? '' : $this->article($objet);

        return $qui . ' ' . $conjugue . ' ' . $article . $objet . $quand . '.';
    }

    /** Le verbe et l'objet, extraits du libellé « Verbe — objet ». */
    private function verbeEtObjet(): array
    {
        $libelle = (string) $this->action;

        if (str_contains($libelle, ' — ')) {
            [$verbe, $objet] = explode(' — ', $libelle, 2);

            return [trim($verbe), trim($objet)];
        }

        return ['Modification', $libelle];
    }

    private function article(string $objet): string
    {
        $feminins = ['commande', 'location', 'facture', 'créance', 'livraison', 'catégorie',
                     'réduction', 'bannière', 'agence', 'dette', 'configuration', 'commission'];

        if (in_array($objet, $feminins, true)) {
            return 'une ';
        }

        return str_starts_with($objet, 'a') || str_starts_with($objet, 'u') ? "un " : 'un ';
    }

    /**
     * LE DÉTAIL, EN FRANÇAIS.
     *
     * Renvoie une liste [libellé, valeur] tirée des données enregistrées. Les
     * noms de colonnes deviennent des mots, les montants prennent leurs
     * espaces, les 0/1 deviennent Non/Oui.
     *
     * Ce qui n'est pas reconnu n'est PAS écarté : un champ inconnu reste
     * affiché sous son nom brut, faute de quoi le récit tairait justement ce
     * qu'on cherche.
     */
    public function elementsLisibles(): array
    {
        $donnees = is_array($this->donnees) ? $this->donnees : [];

        $lignes = [];

        foreach (['parametres', 'saisie'] as $bloc) {
            foreach ((array) ($donnees[$bloc] ?? []) as $cle => $valeur) {
                if ($valeur === null || $valeur === '' || is_array($valeur) && empty($valeur)) {
                    continue;
                }

                $lignes[] = [
                    'libelle' => self::libelleChamp((string) $cle),
                    'valeur'  => self::valeurLisible((string) $cle, $valeur),
                ];
            }
        }

        return $lignes;
    }

    /** Le nom d'un champ, en français. */
    public static function libelleChamp(string $cle): string
    {
        $mots = [
            'plafond_credit'      => 'Plafond de crédit',
            'delai_paiement'      => 'Délai de paiement',
            'commentaire_admin'   => 'Commentaire',
            'motif_refus'         => 'Motif du refus',
            'prix_fournisseur'    => "Prix d'achat fournisseur",
            'prix_achat'          => "Prix d'achat",
            'pourcentage_dalakoun'=> 'Pourcentage DALAKOUN',
            'prix_moyen'          => 'Prix de vente',
            'cout_livraison'      => 'Coût de livraison',
            'mode_paiement'       => 'Mode de paiement',
            'mode_paiement_id'    => 'Mode de paiement',
            'qte_servi'           => 'Quantité servie',
            'nombre_jour'         => 'Nombre de jours',
            'date_livraison'      => 'Date de livraison',
            'date_commande'       => 'Date de commande',
            'date_location'       => 'Date de location',
            'type_affaire'        => "Type d'affaire",
            'seuil_alert'         => "Seuil d'alerte",
            'caution'             => 'Caution',
            'fournisseur'         => 'Fournisseur',
            'fournisseur_id'      => 'Fournisseur',
            'produit'             => 'Produit',
            'produit_id'          => 'Produit',
            'client'              => 'Client',
            'client_id'           => 'Client',
            'livreur'             => 'Livreur',
            'livreur_id'          => 'Livreur',
            'vehicule'            => 'Véhicule',
            'commande'            => 'Commande',
            'location'            => 'Location',
            'facture'             => 'Facture',
            'enlevements'         => "Bons d'enlèvement",
            'demande'             => 'Demande',
            'pourcentage'         => 'Pourcentage',
            'taux'                => 'Taux',
            'montant'             => 'Montant',
            'motif'               => 'Motif',
            'reduction'           => 'Réduction',
            'remise'              => 'Remise',
            'qte'                 => 'Quantité',
            'prix'                => 'Prix',
            'nom'                 => 'Nom',
            'email'               => 'Adresse e-mail',
            'contact'             => 'Téléphone',
            'statut'              => 'Statut',
            'reference'           => 'Référence',
            'numero'              => 'Numéro',
            'description'         => 'Description',
            'date'                => 'Date',
            'du'                  => 'Du',
            'au'                  => 'Au',
            'rep'                 => 'Réponse',
        ];

        if (isset($mots[$cle])) {
            return $mots[$cle];
        }

        // Repli : « adresse_livraison_id » devient « Adresse livraison ».
        $mot = str_replace('_', ' ', preg_replace('/_id$/', '', $cle));

        return ucfirst($mot);
    }

    /** La valeur d'un champ, mise en forme selon ce qu'elle représente. */
    public static function valeurLisible(string $cle, $valeur): string
    {
        if (is_array($valeur)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '…', $valeur));
        }

        if (is_bool($valeur)) {
            return $valeur ? 'Oui' : 'Non';
        }

        $texte = (string) $valeur;

        // Un montant se lit avec ses espaces ; un identifiant, non.
        $montants = ['montant', 'prix', 'plafond', 'cout', 'caution', 'remise', 'total', 'solde'];

        foreach ($montants as $motif) {
            if (str_contains($cle, $motif) && is_numeric($texte)) {
                return number_format((float) $texte, 0, ',', ' ') . ' FCFA';
            }
        }

        if (str_contains($cle, 'delai') && is_numeric($texte)) {
            return $texte . ' jour' . ((int) $texte > 1 ? 's' : '');
        }

        if ((str_contains($cle, 'taux') || str_contains($cle, 'pourcentage')) && is_numeric($texte)) {
            return rtrim(rtrim(number_format((float) $texte, 2, ',', ' '), '0'), ',') . ' %';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $texte)) {
            try {
                return \Carbon\Carbon::parse($texte)->format('d/m/Y');
            } catch (\Throwable $e) {
                return $texte;
            }
        }

        // Un identifiant se lit « n° 12 » — que la cle vienne d une colonne
        // (client_id) ou d un parametre de route (client), qui designent la
        // meme chose et doivent donc s afficher pareil.
        $entites = ['client', 'produit', 'commande', 'facture', 'fournisseur', 'livreur',
                    'location', 'demande', 'pourcentage', 'vehicule', 'agence', 'enlevement',
                    'livraison', 'categorie', 'devis', 'paiement', 'user', 'apporteur'];

        if ((str_ends_with($cle, '_id') || in_array($cle, $entites, true)) && is_numeric($texte)) {
            return 'n° ' . $texte;
        }

        return $texte;
    }

    /** Au-delà, l'écran devient illisible et lent à charger. */
    public const PAR_PAGE = 300;

    /**
     * TOUT CE QU'IL FAUT POUR AFFICHER LE JOURNAL, EN UN SEUL ENDROIT.
     *
     * Ces requêtes vivaient dans AuditController. Depuis que le journal
     * s'affiche AUSSI dans un onglet de « Paramètre », deux écrans en ont
     * besoin : les laisser dans un contrôleur aurait obligé l'autre à les
     * recopier, et deux copies finissent toujours par diverger.
     *
     * @param  array $filtres  user_id, action, du, au, recherche
     */
    public static function journal(array $filtres = []): array
    {
        $requete = self::with(['utilisateur', 'typeUtilisateur']);

        $valeur = function (string $cle) use ($filtres) {
            $v = $filtres[$cle] ?? null;
            return ($v === null || $v === '') ? null : $v;
        };

        if ($v = $valeur('user_id')) {
            $requete->where('user_id', (int) $v);
        }

        // Le libellé porte le verbe en tête (« Validation — commande ») : on
        // filtre donc sur son début, ce qui suffit à distinguer les familles.
        if ($v = $valeur('action')) {
            $requete->where('action', 'like', $v . '%');
        }

        // whereDate compare la seule date : une borne de fin au 18/08 doit
        // inclure les opérations du 18/08 à 23 h, pas s'arrêter à minuit.
        if ($v = $valeur('du')) {
            $requete->whereDate('created_at', '>=', $v);
        }
        if ($v = $valeur('au')) {
            $requete->whereDate('created_at', '<=', $v);
        }

        if ($v = $valeur('recherche')) {
            $terme = '%' . $v . '%';
            $requete->where(function ($q) use ($terme) {
                $q->where('nom_utilisateur', 'like', $terme)
                  ->orWhere('action', 'like', $terme)
                  ->orWhere('url', 'like', $terme)
                  ->orWhere('route_name', 'like', $terme)
                  ->orWhere('adresse_ip', 'like', $terme);
            });
        }

        return [
            'audits'       => $requete->orderByDesc('created_at')->orderByDesc('id')
                                  ->limit(self::PAR_PAGE)->get(),
            'utilisateurs' => self::utilisateursAyantAgi(),
            'actions'      => self::famillesDActions(),
            'filtres'      => [
                'user_id'   => $filtres['user_id']   ?? '',
                'action'    => $filtres['action']    ?? '',
                'du'        => $filtres['du']        ?? '',
                'au'        => $filtres['au']        ?? '',
                'recherche' => $filtres['recherche'] ?? '',
            ],
            'limite'       => self::PAR_PAGE,
        ];
    }

    /**
     * Les comptes qui apparaissent dans le journal.
     *
     * On lit la table `audits` plutôt que `users` : proposer dans le filtre
     * des comptes qui n'ont jamais rien fait n'aide personne, et un compte
     * supprimé doit rester sélectionnable tant que ses traces existent.
     *
     * Pas de `SELECT *` avec `GROUP BY` : MySQL 8 refuse les colonnes hors
     * agrégat. On ne sélectionne que ce qui est groupé.
     */
    public static function utilisateursAyantAgi()
    {
        return self::query()
            ->select('user_id', 'nom_utilisateur')
            ->whereNotNull('user_id')
            ->groupBy('user_id', 'nom_utilisateur')
            ->orderBy('nom_utilisateur')
            ->get();
    }

    /** Les verbes réellement présents, tirés du début des libellés. */
    public static function famillesDActions(): array
    {
        return self::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(fn ($libelle) => trim(explode('—', (string) $libelle)[0]))
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function typeUtilisateur()
    {
        return $this->belongsTo(TypeUser::class, 'type_user_id');
    }

    /**
     * Retire les champs sensibles, à n'importe quelle profondeur.
     */
    public static function nettoyer(array $donnees): array
    {
        $propre = [];

        foreach ($donnees as $cle => $valeur) {
            $nom = mb_strtolower((string) $cle);

            foreach (self::CHAMPS_SENSIBLES as $interdit) {
                if ($nom === $interdit || str_contains($nom, 'password') || str_contains($nom, 'mot_de_passe')) {
                    continue 2;
                }
            }

            if (is_array($valeur)) {
                $propre[$cle] = self::nettoyer($valeur);
            } elseif ($valeur instanceof \Illuminate\Http\UploadedFile) {
                // Un fichier téléversé : on garde son nom, pas son contenu.
                $propre[$cle] = '[fichier] ' . $valeur->getClientOriginalName();
            } elseif (is_object($valeur)) {
                $propre[$cle] = '[objet ' . get_class($valeur) . ']';
            } else {
                $propre[$cle] = $valeur;
            }
        }

        return $propre;
    }

    /**
     * Enregistre une opération. Ne lève jamais d'exception.
     *
     * @param  string  $action   libellé lisible, en français
     * @param  array   $donnees changements ou contexte ; nettoyé avant écriture
     */
    public static function log(string $action, array $donnees = [], ?\Illuminate\Http\Request $requete = null): void
    {
        try {
            $requete = $requete ?: request();
            $utilisateur = Auth::user();

            self::create([
                'user_id'         => $utilisateur?->id,
                'nom_utilisateur' => self::nomLisible($utilisateur),
                'type_user_id'    => $utilisateur?->type_user_id,
                'action'          => mb_substr($action, 0, 191),
                'methode'         => $requete?->method(),
                'url'             => $requete ? mb_substr($requete->fullUrl(), 0, 500) : null,
                'route_name'      => $requete?->route()?->getName(),
                'donnees'         => $donnees ? self::nettoyer($donnees) : null,
                'adresse_ip'      => $requete?->ip(),
                'user_agent'      => $requete ? mb_substr((string) $requete->userAgent(), 0, 500) : null,
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // L'audit ne doit jamais faire échouer l'opération métier : on se
            // contente d'une trace dans le fichier de log.
            Log::warning('Audit non enregistré : ' . $e->getMessage(), ['action' => $action]);
        }
    }

    private static function nomLisible($utilisateur): ?string
    {
        if (!$utilisateur) {
            return null;
        }

        $nom = trim((string) ($utilisateur->nom_prenoms ?? ''));
        $identifiant = trim((string) ($utilisateur->login ?? ''));

        if ($nom === '') {
            $nom = 'Compte n° ' . $utilisateur->id;
        }

        return mb_substr($identifiant !== '' ? $nom . ' (' . $identifiant . ')' : $nom, 0, 191);
    }
}
