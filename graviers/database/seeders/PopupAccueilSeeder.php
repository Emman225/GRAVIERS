<?php

namespace Database\Seeders;

use App\Models\Banniere;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * UNE PUBLICITÉ D'EXEMPLE POUR ESSAYER LE POPUP DE L'ACCUEIL.
 *
 * Le popup ne s'affiche que s'il existe une bannière de type « POPUP » : sans
 * elle, la page d'accueil ne montre rien — c'est voulu, mieux vaut aucun popup
 * qu'un cadre vide. Mais pour ESSAYER la fonction, il faut donc d'abord créer
 * cette bannière, et l'écran de création ne dit pas qu'elle commande le popup.
 *
 * Ce jeu d'essai la crée, avec une image réelle prise dans le site. Il vérifie
 * aussi les deux pièges qui font échouer la mise en place sans le moindre
 * message :
 *
 *   · `type_banniere` est une ÉNUMÉRATION : une valeur absente de la liste n'y
 *     produit pas d'erreur, elle est TRONQUÉE. La bannière s'enregistrerait
 *     avec un type vide et n'apparaîtrait nulle part.
 *
 *   · les images des bannières sont servies depuis « storage/ », un lien
 *     symbolique créé par `php artisan storage:link`. Sans ce lien, l'adresse
 *     répond 404 et le popup s'affiche sans visuel.
 *
 * À lancer :  php artisan db:seed --class=PopupAccueilSeeder
 *
 * Relançable sans risque : la bannière d'exemple est réutilisée, jamais
 * dupliquée.
 */
class PopupAccueilSeeder extends Seeder
{
    /** Le titre sert de repère : la colonne ne prend que 20 caractères. */
    private const TITRE = 'Offre de bienvenue';

    public function run(): void
    {
        if (!$this->colonneAccepteLePopup()) {
            $this->command->error(
                "La colonne `type_banniere` n'accepte pas encore la valeur POPUP."
            );
            $this->command->line(
                "  Lancez d'abord :  php artisan migrate"
            );
            $this->command->line(
                "  ou exécutez le script ajouter-banniere-popup.sql dans phpMyAdmin."
            );
            $this->command->warn(
                "  Sans cela, la bannière s'enregistrerait avec un type VIDE, "
                . "sans erreur, et le popup ne s'afficherait jamais."
            );

            return;
        }

        $image = $this->preparerImage();

        if ($image === null) {
            $this->command->error("Aucune image utilisable n'a été trouvée dans le site.");
            return;
        }

        // Une seule publicité à la fois : la PREMIÈRE bannière active de ce type
        // est celle qui s'affiche. En laisser plusieurs actives rendrait le
        // résultat imprévisible pour qui essaie.
        Banniere::where('type_banniere', 'POPUP')
            ->where('titre', '!=', self::TITRE)
            ->update(['statut' => \Help::$STATUT_INACTIF]);

        $banniere = Banniere::withTrashed()
            ->where('type_banniere', 'POPUP')
            ->where('titre', self::TITRE)
            ->first();

        if ($banniere) {
            $banniere->restore();
            $banniere->update([
                'sous_titre' => "Profitez de nos meilleurs prix sur le gravier, le sable et le ciment.",
                'image'      => $image,
                'num_ordre'  => 1,
                'statut'     => \Help::$STATUT_ACTIF,
            ]);

            $this->command->info("Publicité d'exemple réactivée (bannière n° {$banniere->id}).");
        } else {
            $banniere = Banniere::create([
                'titre'         => self::TITRE,
                'sous_titre'    => "Profitez de nos meilleurs prix sur le gravier, le sable et le ciment.",
                'image'         => $image,
                'num_ordre'     => 1,
                'type_banniere' => 'POPUP',
                'statut'        => \Help::$STATUT_ACTIF,
            ]);

            $this->command->info("Publicité d'exemple créée (bannière n° {$banniere->id}).");
        }

        $this->rapporter($banniere);
    }

    /**
     * L'ÉNUMÉRATION ACCEPTE-T-ELLE « POPUP » ?
     *
     * On lit la définition de la colonne plutôt que de tenter une écriture :
     * une valeur refusée par une énumération est tronquée en silence, et
     * l'échec ne se verrait qu'à l'usage, bien plus tard.
     */
    private function colonneAccepteLePopup(): bool
    {
        $colonne = DB::selectOne("SHOW COLUMNS FROM `banniere` LIKE 'type_banniere'");

        return $colonne && str_contains(strtoupper($colonne->Type), "'POPUP'");
    }

    /**
     * Une image RÉELLE, déposée là où le back-office dépose les siennes.
     *
     * Reprendre le chemin d'une bannière existante ne suffirait pas : elle peut
     * avoir été supprimée du disque. On copie donc un visuel du site vers le
     * dossier des bannières, comme le ferait le formulaire.
     */
    private function preparerImage(): ?string
    {
        $destination = 'productsBanniere/popup-exemple.png';

        if (Storage::disk('public')->exists($destination)) {
            return $destination;
        }

        $sources = [
            public_path('frontend/assets/imgs/theme/produit/banner2.png'),
            public_path('frontend/assets/imgs/banner/banner-13.png'),
            public_path('frontend/assets/imgs/theme/produit/1.png'),
        ];

        foreach ($sources as $source) {
            if (is_file($source)) {
                Storage::disk('public')->put($destination, file_get_contents($source));
                return $destination;
            }
        }

        return null;
    }

    /** Ce qu'il reste à vérifier avant de crier au défaut. */
    private function rapporter(Banniere $banniere): void
    {
        $this->command->newLine();
        $this->command->line("  Titre      : {$banniere->titre}");
        $this->command->line("  Image      : storage/{$banniere->image}");
        $this->command->line("  Adresse    : " . asset('storage/' . $banniere->image));

        $lienPublic = public_path('storage');

        if (!is_link($lienPublic) && !is_dir($lienPublic)) {
            $this->command->newLine();
            $this->command->warn(
                "Le dossier public/storage est absent : les images des bannières "
                . "répondront 404 et le popup s'affichera sans visuel."
            );
            $this->command->line("  Corrigez avec :  php artisan storage:link");
        }

        $this->command->newLine();
        $this->command->info("Ouvrez la page d'accueil du site public.");
        $this->command->line(
            "  Le bandeau des cookies s'affiche d'abord ; le popup vient APRÈS votre choix."
        );
        $this->command->line(
            "  Déjà vu une fois, il ne revient pas : la pastille en bas à gauche le rouvre."
        );
        $this->command->line(
            "  Pour repartir de zéro, videz les cookies et le stockage local du navigateur."
        );
    }
}
