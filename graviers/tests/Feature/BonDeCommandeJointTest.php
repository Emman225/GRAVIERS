<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Support\BonDeCommandeJoint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * LE BON DE COMMANDE S'ENVOIE AUSSI EN PHOTO, EN WORD ET EN EXCEL.
 *
 * Il n'était accepté qu'en PDF, alors qu'un bon de commande arrive le plus
 * souvent en photo — le client le signe, le prend avec son téléphone et
 * l'envoie — ou dans le tableur qui a servi à l'établir.
 *
 * CE QUI NE DOIT PAS SE PERDRE EN CHEMIN : l'extension enregistrée ne vient
 * JAMAIS du nom fourni par le client. Elle est déduite de la liste blanche,
 * et c'est elle qui empêche le dépôt d'un « bon.php » exécutable dans un
 * dossier servi par le serveur.
 */
class BonDeCommandeJointTest extends TestCase
{
    use DatabaseTransactions;

    private function valide(string $nom, string $mime, int $ko = 100): bool
    {
        $fichier = UploadedFile::fake()->create($nom, $ko, $mime);

        return !Validator::make(
            ['fichier' => $fichier],
            ['fichier' => BonDeCommandeJoint::regle()]
        )->fails();
    }

    /** @dataProvider formatsAcceptes */
    public function test_les_formats_bureautiques_courants_sont_acceptes(
        string $nom, string $mime): void
    {
        $this->assertTrue($this->valide($nom, $mime),
            "« $nom » est refusé : le client devrait pouvoir joindre ce format.");
    }

    public static function formatsAcceptes(): array
    {
        return [
            'PDF'  => ['bon.pdf', 'application/pdf'],
            'JPG'  => ['bon.jpg', 'image/jpeg'],
            'PNG'  => ['bon.png', 'image/png'],
            'WEBP' => ['bon.webp', 'image/webp'],
            'DOC'  => ['bon.doc', 'application/msword'],
            'DOCX' => ['bon.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'XLS'  => ['bon.xls', 'application/vnd.ms-excel'],
            'XLSX' => ['bon.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'CSV'  => ['bon.csv', 'text/csv'],
        ];
    }

    /** @dataProvider formatsRefuses */
    public function test_les_formats_dangereux_restent_refuses(
        string $nom, string $mime): void
    {
        $this->assertFalse($this->valide($nom, $mime),
            "« $nom » est accepté : c'est une porte ouverte sur le serveur.");
    }

    public static function formatsRefuses(): array
    {
        return [
            'PHP'        => ['bon.php', 'application/x-httpd-php'],
            'exécutable' => ['bon.exe', 'application/octet-stream'],
            'archive'    => ['bon.zip', 'application/zip'],
            'HTML'       => ['bon.html', 'text/html'],
            'SVG'        => ['bon.svg', 'image/svg+xml'],
        ];
    }

    public function test_un_fichier_trop_gros_est_refuse(): void
    {
        $this->assertFalse(
            $this->valide('bon.pdf', 'application/pdf',
                BonDeCommandeJoint::TAILLE_MAX_KO + 1),
            'Aucune borne de taille : un seul envoi pourrait remplir le disque.');

        $this->assertTrue(
            $this->valide('bon.pdf', 'application/pdf',
                BonDeCommandeJoint::TAILLE_MAX_KO - 1));
    }

    /**
     * L'EXTENSION ENREGISTRÉE NE VIENT JAMAIS DU NOM REÇU.
     *
     * C'est la protection que la nouveauté aurait pu emporter : accepter
     * plusieurs formats invite à reprendre l'extension du fichier, et c'est
     * exactement ce qu'il ne faut pas faire.
     */
    public function test_l_extension_est_prise_dans_la_liste_blanche(): void
    {
        $attendus = [
            'bon.jpeg' => 'jpg',   // ramené à une seule forme
            'bon.JPG'  => 'jpg',   // la casse n'y change rien
            'bon.xlsx' => 'xlsx',
            'bon.pdf'  => 'pdf',
        ];

        foreach ($attendus as $nom => $extension) {
            $fichier = UploadedFile::fake()->create($nom, 10);
            $resultat = BonDeCommandeJoint::nomDeFichier($fichier, 'ENTREPRISE X');

            $this->assertStringEndsWith('.' . $extension, $resultat,
                "« $nom » devrait être enregistré en .$extension.");
        }
    }

    public function test_un_format_inconnu_ne_devient_jamais_executable(): void
    {
        // Ce cas ne devrait pas atteindre l'enregistrement — la validation
        // l'écarte avant — mais la dernière ligne de défense doit tenir seule.
        foreach (['bon.php', 'bon.phtml', 'bon.exe', 'bon'] as $nom) {
            $fichier = UploadedFile::fake()->create($nom, 10);
            $resultat = BonDeCommandeJoint::nomDeFichier($fichier, 'ENTREPRISE X');

            $this->assertStringEndsWith('.pdf', $resultat,
                "« $nom » garde son extension : un fichier exécutable pourrait "
                . 'être déposé dans un dossier servi par le serveur.');
        }
    }

    public function test_le_nom_du_client_ne_peut_pas_sortir_du_dossier(): void
    {
        $fichier = UploadedFile::fake()->create('bon.pdf', 10);
        $resultat = BonDeCommandeJoint::nomDeFichier($fichier, '../../etc/passwd');

        $this->assertStringNotContainsString('/', $resultat);
        $this->assertStringNotContainsString('..', $resultat);
    }

    /** LE CHAMP PROPOSE CE QUE LE SERVEUR ACCEPTE, ET LE DIT. */
    public function test_l_ecran_annonce_les_formats_acceptes(): void
    {
        $client = Client::where('type_client', 'ENTREPRISE')
            ->whereNotNull('user_id')->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client ENTREPRISE en base.');
        }

        $html = $this->actingAs($client->user)->get('/mode-de-paiement')->getContent();

        $this->assertStringContainsString('name="fichier"', $html,
            'Le champ du bon de commande a disparu de la page.');

        // `accept` doit citer chaque format accepté : en proposer moins que le
        // serveur, c'est faire chercher au client un fichier qu'il n'a pas.
        foreach (BonDeCommandeJoint::extensions() as $extension) {
            $this->assertStringContainsString('.' . $extension, $html,
                "Le champ ne propose pas le format .$extension.");
        }
    }
}
