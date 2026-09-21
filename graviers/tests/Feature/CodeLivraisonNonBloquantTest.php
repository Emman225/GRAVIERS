<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UN COURRIEL QUI ÉCHOUE NE DOIT NI CASSER L'AFFECTATION, NI PASSER INAPERÇU.
 *
 * Le 24/08/2026 : un livreur refuse une livraison, le gestionnaire réaffecte la
 * même commande au même livreur — et le client ne reçoit jamais son code. Or
 * l'affectation, elle, avait bien eu lieu : la livraison, le bon d'enlèvement et
 * le décrément de stock étaient déjà écrits.
 *
 * L'envoi n'était ni protégé ni tracé. Une exception — serveur de messagerie
 * injoignable, quota d'envoi de l'hébergeur atteint, adresse invalide —
 * renvoyait une page d'erreur APRÈS coup, sans rien laisser dans le journal, et
 * personne ne pouvait savoir que le code n'était pas parti.
 *
 * Ces contrôles portent sur la STRUCTURE du code : monter une vraie affectation
 * demande une commande, un stock, un fournisseur et un livreur cohérents, et un
 * tel montage prouverait surtout que la fixture est bien faite. Ce qui doit être
 * garanti ici est plus simple et plus durable : aucun envoi de code ne doit
 * rester à nu.
 */
class CodeLivraisonNonBloquantTest extends TestCase
{
    private function orders(): string
    {
        return file_get_contents(app_path('Http/Controllers/OrdersController.php'));
    }

    /**
     * AUCUN ENVOI DE CODE À NU.
     *
     * Le test lit le contrôleur ligne à ligne : tout appel à Mail::send portant
     * un code doit se trouver DANS un bloc try. C'est la garantie qui manquait.
     */
    public function test_aucun_envoi_de_code_n_est_laisse_sans_protection(): void
    {
        $lignes = explode("\n", $this->orders());
        $profondeurTry = 0;
        $aNu = [];

        foreach ($lignes as $i => $ligne) {
            $nue = trim($ligne);

            if ($nue === '' || str_starts_with($nue, '//') || str_starts_with($nue, '*')) {
                continue;
            }

            if (preg_match('/\btry\s*\{/', $nue)) {
                $profondeurTry++;
            }

            if ($profondeurTry > 0 && preg_match('/^\}\s*catch/', $nue)) {
                $profondeurTry--;
            }

            if (str_contains($nue, 'Mail::send(new receptionCode') && $profondeurTry === 0) {
                $aNu[] = ($i + 1) . ' : ' . $nue;
            }
        }

        $this->assertSame([], $aNu,
            "Ces envois de code ne sont pas protégés : une panne de messagerie casserait "
            . "l'affectation APRÈS l'avoir enregistrée.\n" . implode("\n", $aNu));
    }

    /** L'échec doit être écrit dans le journal, sinon il reste indétectable. */
    public function test_un_echec_d_envoi_est_journalise(): void
    {
        $code = $this->orders();

        $this->assertStringContainsString('Code de livraison non envoyé', $code,
            "L'échec d'envoi du code de livraison doit être journalisé.");
        $this->assertStringContainsString("Code d'enlèvement non envoyé", $code,
            "L'échec d'envoi du code d'enlèvement doit être journalisé.");
    }

    /**
     * L'ÉCHEC DOIT ÊTRE DIT AU GESTIONNAIRE, ET RESTER À L'ÉCRAN.
     *
     * La clé doit être PROPRE : Flasher capte « error » et « warning » pour les
     * rejouer en bulle éphémère, et l'avertissement se perdrait — c'est
     * exactement ainsi qu'un code non transmis passe inaperçu.
     */
    public function test_l_echec_est_signale_sur_une_cle_que_flasher_n_avale_pas(): void
    {
        $this->assertSame(2, substr_count($this->orders(), "'code_non_envoye'"),
            "Les DEUX chemins d'affectation doivent signaler un envoi manqué.");

        foreach (['orders/traitement', 'orders/traitementSansLivraison'] as $vue) {
            $this->assertStringContainsString(
                "session('code_non_envoye')",
                file_get_contents(resource_path("views/{$vue}.blade.php")),
                "L'écran {$vue} doit afficher l'avertissement, sinon il n'existe que dans le journal."
            );
        }
    }
}
