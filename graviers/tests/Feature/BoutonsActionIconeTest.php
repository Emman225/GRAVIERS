<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LES BOUTONS D ACTION DES LISTES : UNE ICONE, ET SON NOM AU SURVOL.
 *
 * Une icone seule est muette pour qui ne la connait pas. Un bouton sans
 * `title` oblige a cliquer pour savoir ce qu il fait — sur des ecrans ou l on
 * valide des encaissements et ou l on supprime des lignes.
 *
 * CE QUE CET ESSAI NE COUVRE PAS, ET POURQUOI :
 *
 *   · l ESPACE CLIENT — des clients occasionnels, pas du personnel forme :
 *     « Commander » reduit a une icone se devine mal ;
 *   · les bascules « Actions » d un menu deroulant — l icone seule perd
 *     l affordance du menu ;
 *   · les libelles DYNAMIQUES (« Voir (3) ») — le nombre porte une
 *     information qu aucune icone ne rend.
 *
 * Ces trois familles gardent leur texte, deliberement.
 */
class BoutonsActionIconeTest extends TestCase
{
    /** @return array<int,string> les vues qui affichent une liste DataTable */
    private function vuesAvecListe(): array
    {
        $vues = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($it as $f) {
            if (!$f->isFile() || !str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }

            $chemin = strtr($f->getPathname(), [DIRECTORY_SEPARATOR => '/']);

            // L espace client est hors perimetre : voir l en-tete.
            if (str_contains($chemin, '/views/client/')) {
                continue;
            }

            $code = file_get_contents($chemin);

            if (preg_match('/DataTable[[:space:]]*[(]/i', $code)) {
                $vues[] = $chemin;
            }
        }

        return $vues;
    }

    /**
     * Le « > » qui ferme la balise ouverte a la position donnee.
     *
     * ON NE LIT PAS UNE BALISE BLADE AVEC `[^>]*`. Une expression en contient :
     * `{{route('paye.facture', ['reference' => $ligne->id])}}` porte un « > »
     * dans sa fleche. Le motif s'y arretait, la balise etait coupee en deux, et
     * TOUS les boutons dont les attributs contiennent du Blade echappaient en
     * silence a ces essais — dont celui, precisement, qui portait un crayon
     * sous un titre « Telecharger ».
     */
    private function finDeBalise(string $code, int $i): int
    {
        $guillemet = null;
        $n = strlen($code);

        while ($i < $n) {
            $c = $code[$i];

            if ($guillemet !== null) {
                if ($c === $guillemet) {
                    $guillemet = null;
                }
                $i++;
                continue;
            }

            if ($c === '"' || $c === "'") {
                $guillemet = $c;
                $i++;
                continue;
            }

            if (substr($code, $i, 3) === '{!!') {
                $f = strpos($code, '!!}', $i);
                $i = $f === false ? $n : $f + 3;
                continue;
            }

            if (substr($code, $i, 2) === '{{') {
                $f = strpos($code, '}}', $i);
                $i = $f === false ? $n : $f + 2;
                continue;
            }

            if ($c === '>') {
                return $i;
            }

            $i++;
        }

        return -1;
    }

    /** @return array<int,array{0:string,1:string}> les boutons du corps des tableaux */
    private function boutonsDeListe(string $code): array
    {
        $zones = [];

        if (preg_match_all('/<tbody[[:space:]>]/i', $code, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $t) {
                $fin = stripos($code, '</tbody>', $t[1]);
                $zones[] = [$t[1], $fin === false ? strlen($code) : $fin];
            }
        }

        $boutons = [];

        foreach (['a', 'button'] as $balise) {
            $motif = '/<' . $balise . '[[:space:]]/i';

            if (!preg_match_all($motif, $code, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($m[0] as $t) {
                $dansUneListe = false;

                foreach ($zones as [$a, $b]) {
                    if ($t[1] >= $a && $t[1] < $b) {
                        $dansUneListe = true;
                        break;
                    }
                }

                if (!$dansUneListe) {
                    continue;
                }

                $ouverture = $t[1] + strlen($t[0]) - 1;
                $f = $this->finDeBalise($code, $ouverture);

                if ($f < 0) {
                    continue;
                }

                $ferme = stripos($code, '</' . $balise . '>', $f);

                if ($ferme === false) {
                    continue;
                }

                $boutons[] = [
                    substr($code, $ouverture, $f - $ouverture),
                    substr($code, $f + 1, $ferme - $f - 1),
                ];
            }
        }

        return $boutons;
    }

    /** @return array<int,array{0:string,1:string}> les boutons reduits a une icone */
    private function boutonsIcone(string $vue): array
    {
        $icones = [];

        foreach ($this->boutonsDeListe(file_get_contents($vue)) as [$attrs, $corps]) {
            if (!str_contains($attrs, 'btn') || !preg_match('/<i[[:space:]]/', $corps)) {
                continue;
            }

            if (trim(strip_tags($corps)) === '') {
                $icones[] = [$attrs, $corps];
            }
        }

        return $icones;
    }

    /** UNE ICONE SEULE DIT SON NOM AU SURVOL. */
    public function test_aucun_bouton_icone_ne_reste_muet(): void
    {
        $muets = [];

        foreach ($this->vuesAvecListe() as $vue) {
            foreach ($this->boutonsIcone($vue) as [$attrs, ]) {
                if (!str_contains($attrs, 'title=')) {
                    $muets[] = basename($vue);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($muets)),
            'Des boutons d action ne portent qu une icone, sans titre au '
            . 'survol : l utilisateur doit cliquer pour savoir ce qu ils font — '
            . 'sur des ecrans ou l on valide des encaissements et ou l on '
            . 'supprime des lignes.');
    }

    /**
     * LES BOUTONS GARDENT LEUR DESTINATION.
     *
     * Le passage aux icones ne devait toucher QUE le contenu visible. Un
     * `href` perdu au passage serait une regression invisible a l oeil : la
     * liste garderait son allure et le bouton ne ferait plus rien.
     */
    public function test_les_boutons_gardent_une_destination(): void
    {
        $inertes = [];

        foreach ($this->vuesAvecListe() as $vue) {
            foreach ($this->boutonsIcone($vue) as [$attrs, ]) {
                $agit = str_contains($attrs, 'href=')
                    || str_contains($attrs, 'onclick=')
                    || str_contains($attrs, 'type="submit"')
                    || str_contains($attrs, 'data-');

                if (!$agit) {
                    $inertes[] = basename($vue);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($inertes)),
            'Un bouton d action ne mene nulle part : sa destination a ete '
            . 'perdue en le reduisant a une icone.');
    }

    /**
     * Une icone que la police du theme ne connait pas ne s'affiche pas : elle
     * laisse un carre gris a la place du bouton. Le defaut est invisible pour
     * qui ecrit la vue, et bien visible pour qui s'en sert.
     */
    public function test_toutes_les_icones_existent_dans_la_police(): void
    {
        $connues = [];
        foreach (['public/backend/assets/css/vendors/material-icon-round.css',
                  'public/backend/assets/css/vendors/material-icon.css'] as $css) {
            $chemin = base_path($css);
            if (!is_file($chemin)) {
                continue;
            }
            preg_match_all('/\.(md-[\w]+)/', file_get_contents($chemin), $m);
            $connues = array_merge($connues, $m[1]);
        }
        $connues = array_flip($connues);
        $this->assertNotEmpty($connues, 'La police d icones du theme est introuvable.');

        $absentes = [];
        foreach ($this->vuesAvecListe() as $vue) {
            preg_match_all('/material-icons[^"]*?(md-[\w]+)/', file_get_contents($vue), $m);
            foreach ($m[1] as $icone) {
                if (!isset($connues[$icone])) {
                    $absentes[] = basename($vue) . ' : ' . $icone;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($absentes)),
            'Cette icone ne figure pas dans la police du theme : le bouton '
            . 'affichera un carre gris au lieu de son dessin.');
    }

    /**
     * TOUTE SUPPRESSION EST ROUGE.
     *
     * Le rouge est ce qui arrete la main une seconde avant le clic. Un bouton
     * de suppression en bleu se confond avec « Modifier » ; en noir, il ne
     * previent plus de rien.
     *
     * SEULE EXCEPTION : un bouton DESACTIVE reste gris. Le dernier
     * administrateur actif ne peut pas etre supprime ; le peindre en rouge le
     * ferait passer pour cliquable.
     */
    public function test_toute_suppression_est_rouge(): void
    {
        $pales = [];

        foreach ($this->vuesAvecListe() as $vue) {
            foreach ($this->boutonsDeListe(file_get_contents($vue)) as [$attrs, $corps]) {
                if (!str_contains($attrs, 'btn')) {
                    continue;
                }

                if (!preg_match('/md-delete|md-remove_circle|fa-trash/i', $corps)) {
                    continue;
                }

                if (str_contains($attrs, 'disabled')) {
                    continue;
                }

                if (!preg_match('/\bbtn-danger\b/', $attrs)) {
                    preg_match('/title="([^"]*)"/u', $attrs, $t);
                    $pales[] = basename($vue) . ' : « ' . ($t[1] ?? 'sans titre') . ' »';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($pales)),
            'Un bouton de suppression n est pas rouge : rien n y annonce '
            . 'l irreversible.');
    }

    /**
     * L'ICONE DOIT DIRE CE QUE DIT LE TITRE.
     *
     * Un bouton titre « Telecharger » portait un CRAYON : de quoi croire qu'on
     * s'appretait a corriger le paiement. Le defaut ne se voit pas en lisant le
     * code — le titre et l'icone sont sur la meme ligne, mais on ne lit qu'un
     * des deux.
     */
    public function test_l_icone_ne_contredit_pas_le_titre(): void
    {
        $interdits = [
            'télécharger' => ['md-edit', 'md-delete', 'md-block'],
            'supprimer'   => ['md-edit', 'md-visibility', 'md-get_app'],
            'modifier'    => ['md-delete', 'md-get_app', 'md-block'],
        ];

        $contradictions = [];

        foreach ($this->vuesAvecListe() as $vue) {
            foreach ($this->boutonsIcone($vue) as [$attrs, $corps]) {
                if (!preg_match('/title="([^"]*)"/u', $attrs, $m)) {
                    continue;
                }
                $titre = mb_strtolower($m[1]);

                foreach ($interdits as $mot => $icones) {
                    if (!str_contains($titre, $mot)) {
                        continue;
                    }
                    foreach ($icones as $icone) {
                        if (preg_match('/\b' . preg_quote($icone, '/') . '\b/', $corps)) {
                            $contradictions[] = basename($vue) . ' : « ' . $m[1] . ' » porte ' . $icone;
                        }
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($contradictions)),
            'L icone dit autre chose que le titre : celui qui survole et celui '
            . 'qui regarde ne comprennent pas la meme action.');
    }
}
