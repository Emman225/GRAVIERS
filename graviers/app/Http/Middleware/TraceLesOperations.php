<?php

namespace App\Http\Middleware;

use App\Models\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Journalise toute opération d'ÉCRITURE du back-office.
 *
 * Posé sur le groupe « web », il attrape les créations, modifications,
 * suppressions, validations, encaissements et règlements sans qu'il faille
 * penser à appeler Audit::log() dans chaque contrôleur — ceux-ci restent
 * libres de le faire pour préciser un libellé ou joindre l'avant/après.
 *
 * Deux règles :
 *   · seules les méthodes d'écriture sont tracées, une consultation ne
 *     produit rien ;
 *   · seuls les comptes du back-office le sont. Un client qui commande sur le
 *     site public n'a pas sa place dans un journal d'audit interne, et sans ce
 *     filtre le volume rendrait l'écran illisible.
 *
 * La trace est écrite APRÈS l'action, et seulement si elle a abouti : une
 * requête refusée (403) ou en erreur (500) ne raconte rien d'utile.
 */
class TraceLesOperations
{
    private const METHODES_ECRITURE = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Profils dont les actions sont journalisées : SA, Admin, Gestionnaire, Agent SAV. */
    private const PROFILS_INTERNES = [1, 2, 3, 7];

    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        try {
            if ($this->doitTracer($request, $reponse)) {
                Audit::log($this->libelle($request), $this->donnees($request), $request);
            }
        } catch (\Throwable $e) {
            // Audit::log() se protège déjà ; cette garde couvre le calcul du
            // libellé et des données. L'opération métier reste prioritaire.
        }

        return $reponse;
    }

    private function doitTracer(Request $request, Response $reponse): bool
    {
        if (!in_array($request->method(), self::METHODES_ECRITURE, true)) {
            return false;
        }

        // Une requête refusée ou en erreur n'est pas une opération accomplie.
        if ($reponse->getStatusCode() >= 400) {
            return false;
        }

        // Connexion et déconnexion sont tracées par le contrôleur, qui sait
        // dire si l'identification a réussi. Les tracer ici en plus ferait
        // deux lignes pour un seul événement.
        if ($request->routeIs('show.validLogin', 'show.logout')) {
            return false;
        }

        $utilisateur = Auth::user();

        return $utilisateur && in_array((int) $utilisateur->type_user_id, self::PROFILS_INTERNES, true);
    }

    /**
     * Un libellé lisible, construit du verbe vers l'objet.
     *
     * Le nom de route est la source la plus fiable : il dit l'intention, là où
     * l'URL ne porte que des identifiants.
     */
    private function libelle(Request $request): string
    {
        $route = (string) ($request->route()?->getName() ?: $request->path());
        $repere = mb_strtolower($route);

        $verbe = match (true) {
            str_contains($repere, 'debloqu')                                      => 'Déblocage',
            str_contains($repere, 'bloqu')                                        => 'Blocage',
            str_contains($repere, 'valide') || str_contains($repere, 'valider')   => 'Validation',
            str_contains($repere, 'supprim') || str_contains($repere, 'delete')
                || str_contains($repere, 'destroy')                               => 'Suppression',
            str_contains($repere, 'annul')                                        => 'Annulation',
            str_contains($repere, 'encaiss')                                      => 'Encaissement',
            str_contains($repere, 'regl') || str_contains($repere, 'payer')
                || str_contains($repere, 'paiement')                              => 'Règlement',
            str_contains($repere, 'update') || str_contains($repere, 'modif')
                || str_contains($repere, 'edit')                                  => 'Modification',
            str_contains($repere, 'store') || str_contains($repere, 'register')
                || str_contains($repere, 'create') || str_contains($repere, 'ajout')
                || str_contains($repere, 'nouveau')                               => 'Création',
            $request->method() === 'DELETE'                                       => 'Suppression',
            $request->method() === 'POST'                                         => 'Création',
            default                                                               => 'Modification',
        };

        return $verbe . ' — ' . $this->sujet($request, $repere);
    }

    /** L'objet de l'opération, en français. */
    private function sujet(Request $request, string $repere): string
    {
        $dictionnaire = [
            'enlevement'    => "bon d'enlèvement",
            'apporteur'     => "apporteur d'affaires",
            'gestionnaire'  => 'gestionnaire',
            'configuration' => 'configuration',
            'commission'    => 'commission',
            'fournisseur'   => 'fournisseur',
            'utilisateur'   => 'utilisateur',
            'categorie'     => 'catégorie',
            'reduction'     => 'réduction',
            'livraison'     => 'livraison',
            'banniere'      => 'bannière',
            'vehicule'      => 'véhicule',
            'commande'      => 'commande',
            'location'      => 'location',
            'paiement'      => 'paiement',
            'facture'       => 'facture',
            'creance'       => 'créance',
            'produit'       => 'produit',
            'livreur'       => 'livreur',
            'ticket'        => 'ticket SAV',
            'client'        => 'client',
            'agence'        => 'agence',
            'agent'         => 'agent',
            'admin'         => 'administrateur',
            'devis'         => 'devis',
            'dette'         => 'dette',
            'stock'         => 'stock',
            'promo'         => 'code promo',
            'blog'          => 'article de blog',
            'user'          => 'utilisateur',
        ];

        foreach ($dictionnaire as $motif => $nom) {
            if (str_contains($repere, $motif)) {
                return $nom;
            }
        }

        // Rien de reconnu : le nom de route reste plus parlant qu'un vide.
        return $request->route()?->getName() ?: $request->path();
    }

    /**
     * Les données transmises, sans les champs sensibles ni les jetons.
     */
    private function donnees(Request $request): array
    {
        $saisie = Audit::nettoyer($request->except(['_token', '_method']));

        $parametres = [];
        foreach ($request->route()?->parameters() ?? [] as $cle => $valeur) {
            $parametres[$cle] = is_object($valeur) ? ($valeur->id ?? (string) $valeur) : $valeur;
        }

        return array_filter([
            'parametres' => $parametres ?: null,
            'saisie'     => $saisie ?: null,
        ]);
    }
}
