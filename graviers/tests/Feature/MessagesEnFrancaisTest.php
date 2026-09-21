<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * LES MESSAGES S'ADRESSENT À UN CLIENT IVOIRIEN, EN FRANÇAIS.
 *
 * Constaté le 03/09/2026, capture à l'appui : le panier de l'application
 * client affichait « The code field is required. » Le dossier « lang/fr »
 * existait pourtant — mais il ne contenait qu'une COPIE du fichier anglais,
 * jamais traduite. La langue était bonne, le contenu ne l'était pas : un
 * défaut que rien ne signale, puisque la clé est trouvée.
 *
 * Cet essai vérifie donc les deux : la langue choisie, ET ce qu'elle dit.
 */
class MessagesEnFrancaisTest extends TestCase
{
    /** Les mots qui ne peuvent appartenir qu'à un message anglais. */
    private const MOTS_ANGLAIS = [
        'The ', ' must be ', ' field ', ' does not ', 'Please ', 'Your ',
        'This ', 'We ', 'has been', 'do not match', 'Previous', 'Next',
    ];

    public function test_la_langue_de_l_application_est_le_francais(): void
    {
        $this->assertSame('fr', config('app.locale'),
            "L'application s'adresse à des clients ivoiriens : ses messages "
            . 'doivent partir en français.');
    }

    /** @dataProvider fichiersDeLangue */
    public function test_aucun_message_francais_n_est_reste_en_anglais(string $fichier): void
    {
        $chemin = lang_path('fr/' . $fichier . '.php');

        if (!is_file($chemin)) {
            $this->markTestSkipped("lang/fr/$fichier.php absent.");
        }

        $anglais = [];

        foreach ($this->aplatir(require $chemin) as $cle => $texte) {
            foreach (self::MOTS_ANGLAIS as $mot) {
                if (str_contains($texte, $mot)) {
                    $anglais[] = "$cle : $texte";
                    break;
                }
            }
        }

        $this->assertSame([], $anglais,
            "Ces messages de lang/fr/$fichier.php sont restés en anglais et "
            . "partiront tels quels au client :\n" . implode("\n", array_slice($anglais, 0, 8)));
    }

    public static function fichiersDeLangue(): array
    {
        return [
            'validation' => ['validation'],
            'auth' => ['auth'],
            'passwords' => ['passwords'],
            'pagination' => ['pagination'],
        ];
    }

    /**
     * RIEN NE MANQUE AU FRANÇAIS.
     *
     * Une clé absente retombe silencieusement sur `fallback_locale`, donc sur
     * l'anglais : le défaut réapparaîtrait sans qu'aucune erreur ne le dise.
     */
    public function test_le_francais_couvre_tout_ce_que_couvre_l_anglais(): void
    {
        foreach (['validation', 'auth', 'passwords', 'pagination'] as $fichier) {
            $cheminFr = lang_path('fr/' . $fichier . '.php');
            // L'API n'a pas de dossier « lang/en » : ses messages anglais
            // viennent du cadre. On compare a celui des deux qui existe.
            $cheminEn = lang_path('en/' . $fichier . '.php');

            if (!is_file($cheminEn)) {
                $cheminEn = base_path('vendor/laravel/framework/src/Illuminate'
                    . '/Translation/lang/en/' . $fichier . '.php');
            }

            $this->assertFileExists($cheminFr,
                "lang/fr/$fichier.php est absent : tout retomberait en anglais.");
            $this->assertFileExists($cheminEn,
                "Aucune reference anglaise pour $fichier : l essai ne verifierait rien.");

            $fr = $this->aplatir(require $cheminFr);
            $en = $this->aplatir(require $cheminEn);

            $manquantes = array_diff(array_keys($en), array_keys($fr));

            // L'exemple laissé par Laravel dans « custom » n'est pas une clé.
            $manquantes = array_filter($manquantes,
                fn ($c) => !str_contains($c, 'attribute-name'));

            $this->assertSame([], array_values($manquantes),
                "lang/fr/$fichier.php ne couvre pas tout : ces messages "
                . 'retomberaient en anglais.');
        }
    }

    /** LE MESSAGE DE LA CAPTURE DU 03/09/2026, MOT POUR MOT. */
    public function test_un_champ_obligatoire_le_dit_en_francais(): void
    {
        $erreurs = Validator::make([], ['code' => 'required'])->errors();

        $message = $erreurs->first('code');

        $this->assertStringNotContainsString('The code field is required', $message);
        $this->assertStringContainsString('code promotionnel', $message,
            'Le nom technique du champ ne doit pas remonter tel quel au client.');
    }

    /** LE NOM DES CHAMPS EST CELUI QUE LE CLIENT LIT. */
    public function test_les_champs_portent_leur_nom_francais(): void
    {
        $attendus = [
            'numero_bon' => 'numéro de bon de commande',
            'region_id' => 'région',
            'email' => 'adresse électronique',
            'password' => 'mot de passe',
        ];

        foreach ($attendus as $champ => $lisible) {
            $message = Validator::make([], [$champ => 'required'])->errors()->first($champ);

            $this->assertStringContainsString($lisible, $message,
                "« $champ » remonte sous son nom technique.");
        }
    }

    /** @param array<mixed> $tableau @return array<string,string> */
    private function aplatir(array $tableau, string $prefixe = ''): array
    {
        $plat = [];

        foreach ($tableau as $cle => $valeur) {
            if (is_array($valeur)) {
                $plat += $this->aplatir($valeur, $prefixe . $cle . '.');
            } else {
                $plat[$prefixe . $cle] = (string) $valeur;
            }
        }

        return $plat;
    }
}
