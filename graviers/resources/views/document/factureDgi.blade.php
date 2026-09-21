@php
    // LE DOCUMENT DE LA DGI, À L'IDENTIQUE (lot 102, 17/09/2026). Reproduction
    // mesurée de l'export « Exporter » de la page de vérification FNE : page A4,
    // polices Montserrat Medium / SemiBold de la plateforme, positions, tailles
    // (11 / 10 / 9 / 8 pt), cadres et colonnes relevés dans le PDF de la DGI.
    // Les données sont celles de la page de vérification (DocumentDgi).
    $items = (array) ($dgi['items'] ?? []);
    $arr = fn ($v) => number_format(round((float) $v), 0, '', ' ');
    $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
    $taux = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $entreprise = (array) ($dgi['company'] ?? []);
    $categories = [];
    foreach ($items as $it) {
        $ht = (float) $it['quantity'] * (float) $it['amount'] * (1 - (float) ($it['discount'] ?? 0) / 100);
        foreach ((array) ($it['taxes'] ?? []) as $t) {
            $cle = $t['name'] ?: $t['shortName'];
            $categories[$cle] = $categories[$cle] ?? ['nom' => $cle, 'base' => 0.0, 'taux' => (float) $t['amount'], 'taxes' => 0.0];
            $categories[$cle]['base'] += $ht;
            $categories[$cle]['taxes'] += $ht * (float) $t['amount'] / 100;
        }
    }
    foreach ((array) ($dgi['customTaxes'] ?? []) as $t) {
        $categories['custom:' . $t['name']] = [
            'nom'   => $t['name'],
            'base'  => (float) ($dgi['totalAfterTaxes'] ?? 0),
            'taux'  => (float) $t['amount'],
            'taxes' => (float) ($dgi['totalAfterTaxes'] ?? 0) * (float) $t['amount'] / 100,
        ];
    }
    $policeMedium   = str_replace('\\', '/', public_path('fonts/dgi/Montserrat-Medium.ttf'));
    $policeSemiBold = str_replace('\\', '/', public_path('fonts/dgi/Montserrat-SemiBold.ttf'));
    $logoCi = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('frontend/assets/imgs/logo/cdilogo-dgi.png')));
    $nomEntreprise = $fne_emetteur ?? ($entreprise['name'] ?? '');
    // Le logo DALAKOUN en haut à droite, au-dessus du numéro (demande du 17/09/2026) :
    // seule différence voulue avec l'export de la DGI.
    $logoDalakoun = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path(config('constantes.logo_pdf'))));
    $adresseClient = (string) ($dgi['clientEmail'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $typeDocument }} {{ $fne_numero ?? '' }}</title>
<style>
    @page { size: A4 portrait; margin: 0pt 20pt 20pt 20pt; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: 'MontserratM', sans-serif; color: #000; font-size: 10pt; }
    .sb { font-family: 'MontserratSB', sans-serif; }
    /* ---- en-tête : positions relevées sur l'export de la DGI (page 595,28 × 841,89 pt) */
    .entete { position: relative; height: 328.4pt; }
    .cadre { position: absolute; left: 0; top: 30pt; width: 277.6pt; height: 73.6pt; border: 1pt solid #000; border-radius: 6pt;
             padding: 4.8pt 0 0 6.3pt; font-size: 11pt; }
    .cadre div { height: 15.45pt; line-height: 11pt; white-space: nowrap; overflow: hidden; }
    .infos { position: absolute; left: 0; top: 111.6pt; font-size: 10pt; white-space: nowrap; }
    .infos div, .client div { height: 19.2pt; line-height: 10pt; overflow: hidden; }
    /* Le logo est centré sur la largeur du numéro de facture (de 332 pt à la marge droite). */
    .logo-dalakoun-zone { position: absolute; left: 332pt; top: 66pt; width: 223.3pt; text-align: center; }
    .logo-dalakoun { width: 125pt; }
    .numero { position: absolute; left: 332pt; top: 116pt; font-size: 10pt; line-height: 10pt; white-space: nowrap; }
    .qr { position: absolute; left: 324.6pt; top: 139.3pt; width: 80pt; height: 80pt; }
    .logo { position: absolute; left: 425.3pt; top: 138.2pt; width: 110pt; }
    .client { position: absolute; left: 329.6pt; top: 224.3pt; font-size: 10pt; white-space: nowrap; }
    .client div.titre { height: 17.2pt; }
    /* ---- tableau des articles : colonnes 111,1 / 111 / 55,5 / 27,8 / 27,8 / 55,5 / 55,5 / 111,1 pt */
    table { border-collapse: collapse; width: 555.3pt; }
    td, th { padding: 0; vertical-align: middle; overflow: hidden; }
    .articles th { height: 21.5pt; font-family: 'MontserratSB', sans-serif; font-weight: normal; font-size: 9pt; border: 1pt solid #000; text-align: center; }
    .articles td { height: 19.75pt; font-size: 8pt; border-left: 1pt solid #000; border-right: 1pt solid #000; }
    .articles .c1 { text-align: left; padding-left: 5pt; }
    .articles .c2 { padding-left: 5pt; text-align: left; }
    .articles th.c2 { text-align: center; padding-left: 0; }
    .articles .c3 { text-align: right; padding-right: 6pt; }
    .articles th.c3 { text-align: center; padding-right: 0; }
    .articles .c4 { text-align: center; }
    .articles .c5 { text-align: center; }
    .articles .c6 { text-align: left; padding-left: 4pt; }
    .articles th.c6 { text-align: center; padding-left: 0; }
    .articles .c7 { text-align: center; }
    .articles .c8 { text-align: right; padding-right: 6pt; }
    .articles th.c1 { text-align: left; padding-left: 6pt; }
    .articles th.c8 { text-align: right; padding-right: 6pt; }
    /* ---- totaux : 222,1 / 222,1 / 111,1 pt, lignes de 20,75 pt */
    .totaux td { height: 19.75pt; font-size: 8pt; }
    .totaux .vide { border: 0; }
    .totaux .libelle { text-align: right; padding-right: 6pt; border: 1pt solid #000; }
    .totaux .valeur { text-align: right; padding-right: 6pt; border: 1pt solid #000; }
    .totaux tr:first-child .vide { border-top: 1pt solid #000; }
    /* ---- résumé : 222,1 / 166,6 / 55,5 / 111,1 pt */
    .resume-titre { margin: 0.7pt 0 0 5pt; font-family: 'MontserratSB', sans-serif; font-size: 9pt; line-height: 13pt; }
    .resume { border-top: 1pt solid #000; margin-top: 1.5pt; }
    .resume th { height: 21pt; font-family: 'MontserratSB', sans-serif; font-weight: normal; font-size: 9pt; border-bottom: 1pt solid #000; }
    .resume td { height: 19.75pt; font-size: 8pt; border-bottom: 1pt solid #000; }
    .resume .r1 { text-align: left; padding-left: 5pt; }
    .resume td.r1 { padding-left: 9pt; }
    .resume .r2 { text-align: right; padding-right: 6pt; }
    .resume td.r2 { padding-right: 11pt; }
    .resume .r3 { text-align: center; }
    .resume .r4 { text-align: right; padding-right: 6pt; }
    .resume td.r4 { padding-right: 8pt; }
    .resume td.r2, .resume td.r3 { border-left: 1pt solid #000; border-right: 1pt solid #000; }
    .resume td.r4 { border-left: 1pt solid #000; }
</style>
</head>
<body>
<div class="entete">
    <div class="cadre">
        <div>{{ $nomEntreprise }}</div>
        <div>NCC : {{ $fne_config['ncc'] ?? '' }}</div>
        <div>Régime d'imposition : {{ $fne_config['regime_imposition'] ?? '' }}</div>
        <div>Centre des impôts : {{ $fne_config['centre_impots'] ?? '' }}</div>
    </div>
    <div class="infos">
        <div>RCCM :&nbsp; {{ $fne_config['rccm'] ?? '' }}</div>
        <div>Références bancaires :&nbsp; {{ $fne_config['ref_bancaires'] ?? '' }}</div>
        <div>Établissement :&nbsp; {{ $fne_config['nom_etablissement'] ?? '' }}</div>
        <div>Adresse :&nbsp; {{ $fne_config['adresse_siege'] ?? '' }}</div>
        <div>N° Tel :&nbsp; {{ $fne_config['telephone'] ?? '' }}</div>
        <div>Mail :&nbsp; {{ $fne_config['email_entreprise'] ?? '' }}</div>
        <div>Nom du vendeur :&nbsp; {{ $dgi['clientSellerName'] ?? '' }}</div>
        <div>Nom de PDV :&nbsp; {{ $fne_config['nom_pdv'] ?? '' }}</div>
        <div>Date et heure :&nbsp; {{ $fne_date ?? '' }}</div>
        <div>Mode de paiement :&nbsp; {{ $modePaiement }}</div>
    </div>
    <div class="logo-dalakoun-zone"><img class="logo-dalakoun" src="{{ $logoDalakoun }}" alt="DALAKOUN"></div>
    <div class="numero">{{ $typeDocument }} N° {{ $fne_numero ?? '' }}</div>
    @if(!empty($fne_qr_code))
        <img class="qr" src="{{ $fne_qr_code }}" alt="QR">
    @endif
    <img class="logo" src="{{ $logoCi }}" alt="FNE">
    <div class="client">
        <div class="titre sb">Client</div>
        <div>Nom :&nbsp; {{ $dgi['clientCompanyName'] ?? '' }}</div>
        <div>Adresse :&nbsp; {{ $adresseClient }}</div>
        <div>NCC :&nbsp; {{ $fne_client['ncc'] ?? ($dgi['clientNcc'] ?? '') }}</div>
        {{-- Lot 111 (19/09/2026) : le régime d'imposition du client, à la suite du NCC. --}}
        <div>Régime d'imposition :&nbsp; {{ $fne_client['regime_imposition'] ?? '' }}</div>
    </div>
</div>

<table class="articles">
    <colgroup><col style="width:111.1pt"><col style="width:111pt"><col style="width:55.5pt"><col style="width:27.8pt"><col style="width:27.8pt"><col style="width:55.5pt"><col style="width:55.5pt"><col style="width:111.1pt"></colgroup>
    <thead>
        <tr>
            <th class="c1" style="width:104.1pt">Réf</th>
            <th class="c2" style="width:110pt">Désignation</th>
            <th class="c3" style="width:54.5pt">P.U HT</th>
            <th class="c4" style="width:26.8pt">Qté</th>
            <th class="c5" style="width:26.8pt">Unité</th>
            <th class="c6" style="width:54.5pt">Taxes (%)</th>
            <th class="c7" style="width:54.5pt">Rem. (%)</th>
            <th class="c8" style="width:104.1pt">Montant HT</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $it)
            @php
                $ht = (float) $it['quantity'] * (float) $it['amount'] * (1 - (float) ($it['discount'] ?? 0) / 100);
                $codes = array_map(fn ($t) => ($t['shortName'] ?: $t['name']) . ' (' . $taux($t['amount']) . ')', (array) ($it['taxes'] ?? []));
            @endphp
            <tr>
                <td class="c1" style="width:104.1pt">{{ $it['reference'] }}</td>
                <td class="c2" style="width:104pt">{{ $it['description'] }}</td>
                <td class="c3" style="width:48.5pt">{{ $arr($it['amount']) }}</td>
                <td class="c4" style="width:26.8pt">{{ $qte($it['quantity']) }}</td>
                <td class="c5" style="width:26.8pt">{{ $it['measurementUnit'] }}</td>
                <td class="c6" style="width:50.5pt">{{ implode(', ', $codes) }}</td>
                <td class="c7" style="width:54.5pt">{{ $taux($it['discount'] ?? 0) }}</td>
                <td class="c8" style="width:104.1pt">{{ $arr($ht) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totaux">
    <colgroup><col style="width:222.1pt"><col style="width:222.1pt"><col style="width:111.1pt"></colgroup>
    <tr><td class="vide" style="width:222.1pt"></td><td class="libelle sb" style="width:216.1pt">TOTAL HT</td><td class="valeur sb" style="width:104.1pt">{{ $arr($dgi['totalBeforeTaxes'] ?? 0) }}</td></tr>
    @if((float) ($dgi['totalDiscounted'] ?? 0) > 0)
    <tr><td class="vide" style="width:222.1pt"></td><td class="libelle" style="width:216.1pt">REMISE</td><td class="valeur" style="width:104.1pt">{{ $arr($dgi['totalDiscounted']) }}</td></tr>
    @endif
    <tr><td class="vide" style="width:222.1pt"></td><td class="libelle" style="width:216.1pt">TVA</td><td class="valeur" style="width:104.1pt">{{ $arr($dgi['totalTaxes'] ?? 0) }}</td></tr>
    <tr><td class="vide" style="width:222.1pt"></td><td class="libelle sb" style="width:216.1pt">TOTAL TTC</td><td class="valeur sb" style="width:104.1pt">{{ $arr($dgi['totalAfterTaxes'] ?? 0) }}</td></tr>
    <tr><td class="vide" style="width:222.1pt"></td><td class="libelle" style="width:216.1pt">AUTRES TAXES</td><td class="valeur" style="width:104.1pt">{{ $arr($dgi['totalCustomTaxes'] ?? 0) }}</td></tr>
    @if((float) ($dgi['fiscalStamp'] ?? 0) > 0)
    <tr><td class="vide" style="width:222.1pt"></td><td class="libelle" style="width:216.1pt">TIMBRE FISCAL</td><td class="valeur" style="width:104.1pt">{{ $arr($dgi['fiscalStamp']) }}</td></tr>
    @endif
    <tr><td class="vide" style="width:222.1pt"></td><td class="libelle sb" style="width:216.1pt">TOTAL A PAYER</td><td class="valeur sb" style="width:104.1pt">{{ $arr($dgi['totalDue'] ?? $dgi['amount'] ?? 0) }}</td></tr>
</table>

<div class="resume-titre">RESUME DE LA FACTURE</div>
<table class="resume">
    <colgroup><col style="width:222.1pt"><col style="width:166.6pt"><col style="width:55.5pt"><col style="width:111.1pt"></colgroup>
    <thead>
        <tr>
            <th class="r1" style="width:216.1pt">CATEGORIE</th>
            <th class="r2" style="width:159.6pt">SOUS-TOTAL</th>
            <th class="r3" style="width:54.5pt">TAUX (%)</th>
            <th class="r4" style="width:102.1pt">TOTAL TAXES</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($categories as $c)
            <tr>
                <td class="r1" style="width:212.1pt">{{ $c['nom'] }}</td>
                <td class="r2" style="width:154.6pt">{{ $arr($c['base']) }}</td>
                <td class="r3" style="width:54.5pt">{{ $taux($c['taux']) }}%</td>
                <td class="r4" style="width:102.1pt">{{ $arr($c['taxes']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
