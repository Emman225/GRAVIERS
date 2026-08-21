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
