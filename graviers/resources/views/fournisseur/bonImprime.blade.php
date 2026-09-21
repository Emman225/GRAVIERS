{{-- <html>

<head>
    <script src="{{ asset('backend/assets/js/vendors/color-modes.js') }} "></script>
    <style>
        @page {
            size: 30cm 21cm landscape;
        }

        * {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif'

        }
    </style>
</head>

<div class="card">
    <div class="card-body">
        <div class="container">
            <div class="container-commande">
                <h1>Détail commande</h1>
                <div class="">
                    <table border="1" class="table">
                        <thead>
                            <tr>
                                <th width="40%">Désignation</th>
                                <th width="20%">Prix unitaire</th>
                                <th width="20%">Quantité</th>
                                <th width="20%" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>

                            <tr>
                                <td>
                                    <a class="itemside">
                                        <div class="info">{{ $bon->produit?->nom }}</div>
                                    </a>
                                </td>
                                <td> {{ $produit->prix }} fcfa</td>
                                <td> {{ $bon->qte }} </td>
                                <td class="text-end">Le total fcfa <br> </td>
                            </tr>

                            <tr>
                                <td colspan="4">
                                    <article class="float-end">

                                        <dl class="dlist">

                                            <dd><b class="h5 text-success"> {{ $produit->prix * $bon->qte }} fcfa </b>
                                            </dd>
                                        </dl>

                                    </article>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>


        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-7">
                <h1>Détail livreur</h1>
                <div class="table-responsive">
                    <table border="1" class="table">
                        <thead>
                            <tr>
                                <th width="40%">Nom prénom</th>
                                <th width="20%">matricule du véhicule</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <a class="itemside">
                                        <div class="info">{{ $bon->livraison?->livreur?->user?->nom_prenoms }}</div>
                                    </a>
                                </td>
                                <td> {{ $bon->matricule_vehicule }}</td>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>


            </div>


        </div>
    </div>
</div>


</html> --}}




<html>
    <head>
        <style>
           @page { size: 30cm 21cm landscape; }

        </style>
    </head>
    </html>

        <div class="container" style="max-width: 800px; margin: 0 auto; border: 1px solid #000; padding: 20px;">
            @include('document.partials.entete-bon')

            <div class="title" style="text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 20px;">
                Bon de livraison N° <span style="font-weight:bold"> {{$bon->code_enleve}} </span>
                {{-- Lot 111 (19/09/2026) : le numéro du bon en code-barres (Code 128). --}}
                {!! \App\Services\CodeBarres::bloc($bon->code_enleve, 40) !!}
            </div>

            {{-- <div class="info-box">
                <p>En date du: <span style="font-weight:bold">{{$enlevement->livraison?->date_livraison}}</span> </p>
                <p>Référence fournisseur: <span style="font-weight:bold">{{ $enlevement->fournisseur?->nom_prenoms }}</span></p>
                <p>Adresse <span style="font-weight:bold">{{ $enlevement->fournisseur?->adresse_geo }}</span></p>
                <p>Contact: <span style="font-weight:bold">{{ $enlevement->fournisseur?->contact1 }}</span></p>
            </div> --}}

            <div class="info-box" style="border: 1px solid #000; padding: 10px; margin-bottom: 20px;  ">
                {{-- Ce bon est remis au fournisseur : il doit lui dire quand
                     l'enlèvement a lieu et qui se présente pour le charger. Les
                     libellés retombent sur « - » plutôt que de rester vides, un
                     blanc laissant croire à une donnée oubliée. --}}
                <p style="font-weight:bold; margin-bottom: 6px;">Détail livreur</p>
                <p>En date du : <span style="font-weight:bold">{{ $bon->livraison?->date_livraison ? \Carbon\Carbon::parse($bon->livraison->date_livraison)->format('d/m/Y') : '-' }}</span></p>
                <p>Nom et prénoms : <span style="font-weight:bold">{{ $bon->livraison?->livreur?->user?->nom_prenoms ?: '-' }}</span></p>
                <p>Contact : <span style="font-weight:bold">{{ $bon->livraison?->livreur?->user?->contact ?: '-' }}</span></p>
                <p>Matricule du véhicule : <span style="font-weight:bold">{{ $bon->matricule_vehicule ?: '-' }}</span></p>
                {{-- <p>Télécopie</p>
                <p>Lieu de livraison:  <span style="font-weight:bold">{{$enlevement->livraison?->AdresseLivraison->complement_adresse}}</span></p> --}}
            </div>

            <table style=" width: 100%; border-collapse: collapse; margin-bottom: 20px;  ">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Désignation  </th>
                        <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Qté  </th>
                        <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Prix unitaire  </th>
                        <th style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #f0f0f0;">Total  </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1px solid #000; padding: 10px; text-align: left;">{{ $bon->produit?->nom ?: '-' }}</td>
                        <td style="border: 1px solid #000; padding: 10px; text-align: left;">
                            {{-- La quantité SERVIE fait foi dès qu'elle est saisie : c'est elle
                                 qui sera payée au fournisseur (Enlevement::quantiteAPayer). --}}
                            {{ rtrim(rtrim(number_format($bon->quantiteAPayer(), 2, ',', ' '), '0'), ',') }}
                            @if ($bon->quantiteDiffereDeLaCommande())
                                <br>
                                <span style="font-size: 11px; color: #555;">
                                    quantité demandée : {{ rtrim(rtrim(number_format((float) $bon->qte, 2, ',', ' '), '0'), ',') }}
                                </span>
                            @endif
                        </td>
                        <td style="border: 1px solid #000; padding: 10px; text-align: left;">{{ number_format((float) ($produit?->prix ?? 0), 0, '', ' ') }} fcfa</td>
                        {{-- Le bleu du logo, à la place du jaune (lot 84, 15/09/2026). --}}
                        <td style="border: 1px solid #000; padding: 10px; text-align: left; background-color: #1c57a3; color: #fff; font-weight: bold;">{{ number_format((float) ($produit?->prix ?? 0) * $bon->quantiteAPayer(), 0, '', ' ') }} fcfa</td>
                    </tr>
                    {{-- <tr class="total-row" style="">
                        <td colspan="3" style="border: 1px solid #000; padding: 10px; text-align: left;">Total</td>
                        <td  style="border: 1px solid #000; padding: 10px; text-align: left;">fcfa</td>
                    </tr> --}}
                </tbody>
            </table>

            {{-- Lot 112 (19/09/2026) : deux colonnes par un TABLEAU (« display:flex » ignoré par DomPDF). --}}
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


