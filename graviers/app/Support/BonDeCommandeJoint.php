<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * LE BON DE COMMANDE QUE LE CLIENT JOINT À SA COMMANDE.
 *
 * Il n'était accepté qu'en PDF, alors qu'un bon de commande arrive le plus
 * souvent en PHOTO — le client le signe, le prend avec son téléphone et
 * l'envoie — ou dans le tableur qui a servi à l'établir. Il fallait donc le
 * convertir avant de pouvoir commander.
 *
 * LA RÈGLE EST ICI, ET NULLE PART AILLEURS. Elle était recopiée sur quatre
 * écrans — commande, commande issue d'un devis, location, demande de livraison —
 * qui se seraient inévitablement écartés l'un de l'autre : un client aurait pu
 * joindre une photo à sa commande et se l'entendre refuser sur sa location.
 *
 * ATTENTION, L'EXTENSION N'EST JAMAIS CELLE DU FICHIER REÇU. Elle est déduite
 * de ce que la VALIDATION a reconnu, et prise dans la liste ci-dessous : le nom
 * fourni par le client ne touche jamais le disque. C'est ce qui empêche le
 * dépôt d'un « bon.php » exécutable dans un dossier servi par le serveur.
 */
class BonDeCommandeJoint
{
    /**
     * Ce qu'on accepte, et l'extension sous laquelle on l'enregistre.
     *
     * Le PDF reste en tête : c'est la forme la plus lisible pour le
     * gestionnaire, et celle qui s'affiche directement dans l'écran de
     * traitement.
     */
    public const FORMATS = [
        'pdf'  => 'pdf',
        'jpg'  => 'jpg',
        'jpeg' => 'jpg',
        'png'  => 'png',
        'webp' => 'webp',
        'gif'  => 'gif',
        'doc'  => 'doc',
        'docx' => 'docx',
        'xls'  => 'xls',
        'xlsx' => 'xlsx',
        'csv'  => 'csv',
    ];

    /**
     * La taille maximale, en kilo-octets.
     *
     * 2 Mo suffisaient pour un PDF ; une photo prise au téléphone les dépasse
     * presque toujours. Accepter les images sans relever cette borne aurait
     * rendu la nouveauté inutilisable.
     */
    public const TAILLE_MAX_KO = 5120;

    /** Les extensions acceptées, sans doublon. */
    public static function extensions(): array
    {
        return array_keys(self::FORMATS);
    }

    /** La règle de validation, `nullable` ou `required` selon l'écran. */
    public static function regle(bool $obligatoire = true): string
    {
        return ($obligatoire ? 'required' : 'nullable')
            . '|mimes:' . implode(',', self::extensions())
            . '|max:' . self::TAILLE_MAX_KO;
    }

    /** Ce que l'attribut `accept` du champ doit proposer. */
    public static function accept(): string
    {
        return '.' . implode(',.', self::extensions());
    }

    /** La phrase affichée sous le champ, et dans les messages d'erreur. */
    public static function formatsLisibles(): string
    {
        return 'PDF, image (JPG, PNG, WEBP, GIF), Word (DOC, DOCX) '
            . 'ou Excel (XLS, XLSX, CSV)';
    }

    /** Les messages d'erreur, dans les mots du client. */
    public static function messages(string $champ = 'fichier'): array
    {
        return [
            $champ . '.required' => 'Veuillez joindre votre bon de commande',
            $champ . '.mimes' => 'Le bon de commande doit être au format '
                . self::formatsLisibles() . '.',
            $champ . '.max' => 'Le fichier ne doit pas dépasser '
                . (int) (self::TAILLE_MAX_KO / 1024) . ' Mo',
        ];
    }

    /**
     * Le nom sous lequel le fichier est enregistré.
     *
     * L'extension vient de la liste blanche, JAMAIS du nom reçu. Un format
     * inconnu — que la validation aurait dû écarter avant d'arriver ici —
     * retombe sur « pdf », qui n'est exécutable nulle part.
     */
    public static function nomDeFichier(UploadedFile $fichier, string $prefixe): string
    {
        $extension = strtolower($fichier->getClientOriginalExtension());
        $extension = self::FORMATS[$extension] ?? 'pdf';

        // Le nom du client entre dans le nom du fichier : on n'y laisse que de
        // quoi le reconnaître, et rien qui puisse sortir du dossier. Le POINT
        // est écarté lui aussi — il n'a rien à faire dans un nom de client, et
        // c'est ce qui compose « .. ».
        $prefixe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $prefixe);

        return 'bon-' . trim($prefixe, '_') . '-' . date('YmdHis') . '.' . $extension;
    }
}
