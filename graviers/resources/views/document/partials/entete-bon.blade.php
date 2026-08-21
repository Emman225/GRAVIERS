@php
    use App\Models\Configuration;

    // En-tête commun aux bons de livraison (exemplaire client et exemplaire
    // fournisseur) : les deux documents portaient le même bloc, dupliqué, avec
    // le logo commenté et trois libellés laissés vides.
    //
    // La mise en page passe par un tableau et non par flexbox : dompdf, qui
    // produit ces PDF, ignore `display: flex` et empilerait les deux logos.

    $imageEmbarquee = function (?string $chemin): ?string {
        // Les images sont encodées dans le document : dompdf ne va pas
        // toujours chercher une URL, et un logo introuvable ne doit pas
        // empêcher le bon de s'imprimer.
        if (!$chemin) {
            return null;
        }

        $fichier = public_path($chemin);

        if (!is_file($fichier)) {
            return null;
        }

        $extension = strtolower(pathinfo($fichier, PATHINFO_EXTENSION)) ?: 'png';

        return 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($fichier));
    };

    $logoPlateforme = $imageEmbarquee(config('constantes.logo'));
    $logoEntreprise = $imageEmbarquee(config('constantes.logo_pdf'));

    $fiche = Configuration::first();

    $raisonSociale = $fiche?->raison_sociale ?: 'DALAKOUN SARL';

    // Le site : celui de la plateforme, tiré de l'URL de l'application. En
    // développement (localhost) la ligne n'a pas de sens sur un document.
    $hote = parse_url((string) config('app.url'), PHP_URL_HOST);
    $siteInternet = ($hote && !in_array($hote, ['localhost', '127.0.0.1'], true)) ? $hote : null;

    // Un libellé sans valeur n'apprend rien au lecteur : on n'imprime que les
    // lignes renseignées dans la fiche de l'entreprise.
    $coordonnees = array_filter([
        'Adresse'       => $fiche?->adresse_siege,
        'Téléphone'     => $fiche?->telephone,
        'Adresse mail'  => $fiche?->email_entreprise,
        'Site internet' => $siteInternet,
    ], fn ($valeur) => trim((string) $valeur) !== '');
@endphp

<style>
    /* Les paragraphes des cadres d'information gardent la marge par défaut
       (1 em haut et bas). Une fois les libellés manquants renseignés, cela
       suffisait à repousser le cadre de signature sur une seconde page. */
    .info-box p { margin: 4px 0; }
</style>

<table class="header" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr>
        <td style="width: 30%; vertical-align: top; padding: 0;">
            @if ($logoPlateforme)
                <img src="{{ $logoPlateforme }}" alt="GRAVIER.COM" style="width: 80px; height: auto;">
            @endif
        </td>
        <td style="vertical-align: top; padding: 0; text-align: right; font-size: 12px;">
            @if ($logoEntreprise)
                <img src="{{ $logoEntreprise }}" alt="{{ $raisonSociale }}" style="width: 190px; height: auto;">
                <br>
            @else
                <span style="font-weight: bold;">{{ $raisonSociale }}</span><br>
            @endif

            @foreach ($coordonnees as $libelle => $valeur)
                <span>{{ $libelle }} : {{ $valeur }}</span><br>
            @endforeach
        </td>
    </tr>
</table>
