

 <html>
 <head>
     {{-- <style>
        @page { size: 30cm 21cm landscape; }

     </style> --}}
 </head>
 </html>

     <div class="container" style="max-width: 800px; margin: 0 auto; border: 1px solid #000; padding: 20px;">
         @include('document.partials.entete-bon')

         <div class="title" style="text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 6px;">
             Bon de livraison N° <span style="font-weight:bold"> {{$enlevement->code_enleve}} </span>
         </div>
         {{-- Lot 111 (19/09/2026) : le numéro du bon en code-barres (Code 128), lisible à la douchette. --}}
         {!! \App\Services\CodeBarres::bloc($enlevement->code_enleve) !!}

         @php
             $livraison = $enlevement->livraison;
             $fournisseur = $enlevement->fournisseur;
             $client = $livraison?->client;
             $adresse = $livraison?->AdresseLivraison;
             $produit = $enlevement->produit;
         @endphp

         <div class="info-box">
             {{-- La date brute de la base (2026-07-20 00:00:00) n'a pas sa place sur
                  un document remis au client. --}}
             <p>En date du : <span style="font-weight:bold">{{ $livraison?->date_livraison ? \Carbon\Carbon::parse($livraison->date_livraison)->format('d/m/Y') : '-' }}</span></p>
             <p>Référence fournisseur : <span style="font-weight:bold">{{ trim(($fournisseur?->nom ?? '').' '.($fournisseur?->prenom ?? '')) ?: ($fournisseur?->user?->nom_prenoms ?? '-') }}</span></p>
             <p>Adresse : <span style="font-weight:bold">{{ $fournisseur?->adresse_geo ?? '-' }}</span></p>
             <p>Contact : <span style="font-weight:bold">{{ $fournisseur?->contact1 ?? $fournisseur?->user?->contact ?? '-' }}</span></p>
         </div>

         <div class="info-box" style="border: 1px solid #000; padding: 10px; margin-bottom: 20px;  ">
             <p>Client : <i><span style="font-weight:bold">{{ ($client?->display_name ?? '') ?: '-' }}</span></i></p>
             <p>Adresse : <span style="font-weight:bold">{{ $adresse?->affichage ?? $adresse?->complement_adresse ?? '-' }}</span> </p>
             <p>Téléphone : <span style="font-weight:bold">{{ $client?->contact1 ?? '-' }}</span></p>
             <p>E-mail : <span style="font-weight:bold">{{ $client?->user?->email ?? '-' }}</span></p>
             {{-- Lot 112 (19/09/2026) : l'identité fiscale du client, quand sa fiche la porte
                  (entreprise) ; rien pour un particulier, plutôt que deux lignes vides. --}}
             @php
                 $nccClient = preg_replace('/\s+/', '', (string) ($client?->ncc_clt ?? ''));
                 $regimeClient = trim((string) ($client?->regime_imposition ?? ''));
                 $codeRegimeClient = \App\Support\RegimeImposition::code($regimeClient);
                 if ($codeRegimeClient) {
                     $regimeClient = $codeRegimeClient . ' (' . \App\Support\RegimeImposition::libelle($codeRegimeClient) . ')';
                 }
             @endphp
             @if ($nccClient !== '' || $regimeClient !== '')
                 <p>
                     @if ($nccClient !== '')NCC : <span style="font-weight:bold">{{ $nccClient }}</span>@endif
                     @if ($nccClient !== '' && $regimeClient !== '') &nbsp;·&nbsp; @endif
                     @if ($regimeClient !== '')Régime d'imposition : <span style="font-weight:bold">{{ $regimeClient }}</span>@endif
                 </p>
             @endif
             <p>Lieu de livraison :  <span style="font-weight:bold">{{ $adresse?->affichage ?? $adresse?->complement_adresse ?? '-' }}</span></p>
         </div>

         <table style=" width: 100%; border-collapse: collapse; margin-bottom: 20px;  ">
             <thead>
                 <tr>
                     <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Désignation  </th>
                     <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Qté  </th>
                     {{-- La date d'enlèvement ou de livraison de la ligne (lot 84, 15/09/2026). --}}
                     <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Date enlèvement ou livraison</th>
                 </tr>
             </thead>
             <tbody>
                 @php
                     // Prix unitaire : prix réellement facturé sur la ligne de commande si dispo,
                     // sinon prix_moyen du produit (fallback). Cohérent avec le refactor prix personnalisé.
                     $puBon = optional(optional($livraison)->detailCommande)->prix ?? ($produit?->prix_moyen ?? 0);

                     // La quantité qui fait foi : celle SERVIE par le fournisseur
                     // dès qu'elle est saisie, la demandée tant que le bon n'est
                     // pas encore servi. Même règle que le paiement du
                     // fournisseur (Enlevement::quantiteAPayer) : le bon et le
                     // règlement ne peuvent pas annoncer deux quantités.
                     $qteBon = $enlevement->quantiteAPayer();
                     // La date imprimée : livraison effective, sinon validation du fournisseur, sinon date prévue.
                     $dateDuBon = $dateDuBon ?? \App\Services\BonDeLivraisonClient::dateDuBon($enlevement);
                     $historique = $historique ?? \App\Services\BonDeLivraisonClient::historique($enlevement);
                     $recap = $recap ?? \App\Services\BonDeLivraisonClient::recapParProduit($enlevement, $historique);
                     $commandeDuBon = $commande ?? \App\Services\BonDeLivraisonClient::commandeDe($enlevement);
                     $fmtQte = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ' '), '0'), ',');
                 @endphp
                 <tr>
                     <td style="border: 1px solid #000; padding: 10px; text-align: left;">{{ $produit?->nom ?? '-' }}</td>
                     <td style="border: 1px solid #000; padding: 10px; text-align: left;">
                         {{ rtrim(rtrim(number_format($qteBon, 2, ',', ' '), '0'), ',') }} x {{ number_format($puBon, 0, '', ' ') }} fcfa
                         {{-- Le bon a été servi autrement que demandé : on le dit, sinon
                              le document semblerait contredire la commande. --}}
                         @if ($enlevement->quantiteDiffereDeLaCommande())
                             <br>
                             <span style="font-size: 11px; color: #555;">
                                 quantité demandée : {{ rtrim(rtrim(number_format((float) $enlevement->qte, 2, ',', ' '), '0'), ',') }}
                             </span>
                         @endif
                     </td>
                     <td style="border: 1px solid #000; padding: 10px; text-align: left;">{{ $dateDuBon ? $dateDuBon->format('d/m/Y') : '-' }}</td>
                 </tr>
                 {{-- Le bleu du logo, à la place du jaune (lot 84, 15/09/2026). --}}
                 <tr class="total-row" style="background-color: #1c57a3; color: #fff;">
                     <td style="border: 1px solid #000; padding: 10px; text-align: left; font-weight: bold;">Total</td>
                     <td style="border: 1px solid #000; padding: 10px; text-align: left; font-weight: bold;">{{ number_format($qteBon * $puBon, 0, '', ' ') }} fcfa</td>
                     <td style="border: 1px solid #000; padding: 10px;"></td>
                 </tr>
             </tbody>
         </table>

         @if ($commandeDuBon && count($historique) > 0)
             {{-- L'HISTORIQUE DES ENLÈVEMENTS DE LA COMMANDE (lot 84, 15/09/2026) : une
                  commande livrée en plusieurs fois rappelle, sur chaque bon, ce qui a
                  déjà été enlevé et ce qui reste, jusqu'à la livraison totale. --}}
             <p style="font-weight: bold; margin: 0 0 6px;">Historique des enlèvements de la commande N° {{ $commandeDuBon->numero }}</p>
             <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 12px;">
                 <thead>
                     <tr>
                         <th style="border: 1px solid #000; padding: 6px; text-align: left; background-color: #f0f0f0;">N° bon</th>
                         <th style="border: 1px solid #000; padding: 6px; text-align: left; background-color: #f0f0f0;">Désignation</th>
                         <th style="border: 1px solid #000; padding: 6px; text-align: right; background-color: #f0f0f0;">Qté</th>
                         <th style="border: 1px solid #000; padding: 6px; text-align: left; background-color: #f0f0f0;">Date enlèvement ou livraison</th>
                         <th style="border: 1px solid #000; padding: 6px; text-align: right; background-color: #f0f0f0;">Cumul livré</th>
                     </tr>
                 </thead>
                 <tbody>
                     @foreach ($historique as $h)
                         <tr style="{{ $h['courant'] ? 'font-weight: bold;' : '' }}">
                             <td style="border: 1px solid #000; padding: 6px;">{{ $h['code'] }}{{ $h['courant'] ? ' (ce bon)' : '' }}</td>
                             <td style="border: 1px solid #000; padding: 6px;">{{ $h['produit'] }}</td>
                             <td style="border: 1px solid #000; padding: 6px; text-align: right;">{{ $fmtQte($h['qte']) }} {{ $h['unite'] }}</td>
                             <td style="border: 1px solid #000; padding: 6px;">{{ $h['date'] ? $h['date']->format('d/m/Y') : '-' }}</td>
                             <td style="border: 1px solid #000; padding: 6px; text-align: right;">{{ $fmtQte($h['cumul']) }} {{ $h['unite'] }}</td>
                         </tr>
                     @endforeach
                 </tbody>
             </table>
             <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px;">
                 <thead>
                     <tr>
                         <th style="border: 1px solid #000; padding: 6px; text-align: left; background-color: #1c57a3; color: #fff;">Produit</th>
                         <th style="border: 1px solid #000; padding: 6px; text-align: right; background-color: #1c57a3; color: #fff;">Quantité commandée</th>
                         <th style="border: 1px solid #000; padding: 6px; text-align: right; background-color: #1c57a3; color: #fff;">Livré à ce jour</th>
                         <th style="border: 1px solid #000; padding: 6px; text-align: right; background-color: #1c57a3; color: #fff;">Reste à livrer</th>
                     </tr>
                 </thead>
                 <tbody>
                     @foreach ($recap as $r)
                         <tr>
                             <td style="border: 1px solid #000; padding: 6px;">{{ $r['produit'] }}</td>
                             <td style="border: 1px solid #000; padding: 6px; text-align: right;">{{ $fmtQte($r['commandee']) }} {{ $r['unite'] }}</td>
                             <td style="border: 1px solid #000; padding: 6px; text-align: right;">{{ $fmtQte($r['livree']) }} {{ $r['unite'] }}</td>
                             <td style="border: 1px solid #000; padding: 6px; text-align: right; {{ $r['reste'] > 0 ? '' : 'color: #1c8a3a; font-weight: bold;' }}">{{ $r['reste'] > 0 ? $fmtQte($r['reste']) . ' ' . $r['unite'] : 'Livraison totale' }}</td>
                         </tr>
                     @endforeach
                 </tbody>
             </table>
         @endif

         {{-- Lot 112 (19/09/2026) : deux colonnes par un TABLEAU. « display:flex » est ignoré par
              DomPDF, qui empilait les deux blocs ; le cadre de signature glissait alors sur une
              seconde page dès que le bon gagnait quelques lignes. --}}
         <table class="footer" style="width: 100%; border-collapse: collapse;">
             <tr>
                 <td style="vertical-align: top; padding: 0; width: 52%;">
                     <p style="margin: 4px 0;">Observation(s) lors de la réception</p>
                 </td>
                 <td style="vertical-align: top; padding: 0; width: 48%;">
                     <div class="footer-box" style="border: 1px solid #000; padding: 10px; height: 100px;">
                         <p style="margin: 0;">Nom, signature et date du réceptionnaire</p>
                     </div>
                 </td>
             </tr>
         </table>
     </div>


 {{-- <!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de livraison</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #000;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .logo {
            border: 1px solid #000;
            padding: 10px;
            font-size: 24px;
            font-weight: bold;
        }
        .title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .info-box {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        .total-row {
            background-color: yellow;
        }
        .footer {
            display: flex;
            justify-content: space-between;
        }
        .footer-box {
            border: 1px solid #000;
            padding: 10px;
            width: 45%;
            height: 100px;
        }
    </style>

</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><img width="150px" src="{{asset(config('constantes.logo_pdf'))}}" alt=""></div>
            <div>
                <p>02 BP 578 Abidjan 02</p>
                <p>Téléphone : 07 97 85 68 27</p>
                <p>Télécopie :</p>
                <p>Adresse mail :</p>
                <p>Site internet :</p>
            </div>
        </div>

        <div class="title">
            Bon de livraison N°
        </div>

        <div class="info-box">
            <p>En date du: <span style="font-weight:bold">{{$enlevement->livraison?->date_livraison}}</p>
            <p>Référence fournisseur: <span style="font-weight:bold">{{ $enlevement->fournisseur?->nom_prenoms }}</p>
            <p>Ville: </p>
            <p>Pays</p>
        </div>

        <div class="info-box">
            <h3>Client: <i><span style="font-weight:bold">{{ $enlevement->livraison?->client?->display_name }}</h3>
            <p>Adresse :  {{$enlevement->livraison?->AdresseLivraison->complement_adresse}}</p>
            <p>Téléphone : {{$enlevement->livraison?->client?->contact1}}</p>
            <p>Télécopie</p>
            <p>Lieu de livraison : {{$enlevement->livraison?->AdresseLivraison->complement_adresse}}</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th>Qté</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $enlevement->produit?->nom }}</td>
                    <td>{{ $enlevement->qte }} x {{ $enlevement->produit?->unite }} fcfa</td>
                </tr>
                <tr class="total-row">
                    <td>Total</td>
                    <td>{{$enlevement->qte * $enlevement->produit?->unite}}fcfa</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <div class="footer-box">
                <p>Observation(s) lors de la réception</p>
            </div>
            <div class="footer-box">
                <p>Nom, signature et date du réceptionnaire</p>
            </div>
        </div>
    </div>
</body>
</html> --}}

