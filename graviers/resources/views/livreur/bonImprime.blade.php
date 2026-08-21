

 <html>
 <head>
     {{-- <style>
        @page { size: 30cm 21cm landscape; }

     </style> --}}
 </head>
 </html>

     <div class="container" style="max-width: 800px; margin: 0 auto; border: 1px solid #000; padding: 20px;">
         @include('document.partials.entete-bon')

         <div class="title" style="text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 20px;">
             Bon de livraison N° <span style="font-weight:bold"> {{$enlevement->code_enleve}} </span>
         </div>

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
             <p>Lieu de livraison :  <span style="font-weight:bold">{{ $adresse?->affichage ?? $adresse?->complement_adresse ?? '-' }}</span></p>
         </div>

         <table style=" width: 100%; border-collapse: collapse; margin-bottom: 20px;  ">
             <thead>
                 <tr>
                     <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Désignation  </th>
                     <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Qté  </th>
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
                 </tr>
                 <tr class="total-row" style="background-color: yellow;">
                     <td style="border: 1px solid #000; padding: 10px; text-align: left;">Total</td>
                     <td style="border: 1px solid #000; padding: 10px; text-align: left;">{{ number_format($qteBon * $puBon, 0, '', ' ') }} fcfa</td>
                 </tr>
             </tbody>
         </table>

         <div class="footer" style="display: flex; justify-content: space-between;">
             <div class="footer-box">
                 <p>Observation(s) lors de la réception</p>
             </div>
             <div class="footer-box" style="border: 1px solid #000; padding: 10px; width: 45%; height: 100px;">
                 <p>Nom, signature et date du réceptionnaire</p>
             </div>
         </div>
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
            <p>Référence Fournisseur: <span style="font-weight:bold">{{ $enlevement->fournisseur?->nom_prenoms }}</p>
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

