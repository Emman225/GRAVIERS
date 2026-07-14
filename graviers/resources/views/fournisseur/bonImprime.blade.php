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
                                        <div class="info">{{ $bon->produit->nom }}</div>
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
                                        <div class="info">{{ $bon->livraison->livreur->user->nom_prenoms }}</div>
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
            <div class="header" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                <div>
                   {{-- <img width="150px" src="{{asset('backend/assets/imgs/theme/logoAvecFond.jpg')}}" alt=""> --}}
                </div>
                <div>
                    <p>02 BP 578 Abidjan 02</p>
                    <p>Téléphone : 07 97 85 68 27</p>
                    <p>Télécopie :</p>
                    <p>Adresse mail :</p>
                    <p>Site internet :</p>
                </div>
            </div>

            <div class="title" style="text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 20px;">
                Bon de livraison N° <span style="font-weight:bold"> {{$bon->code_enleve}} </span>
            </div>

            {{-- <div class="info-box">
                <p>En date du: <span style="font-weight:bold">{{$enlevement->livraison->date_livraison}}</span> </p>
                <p>Référence Fournisseur: <span style="font-weight:bold">{{ $enlevement->fournisseur->nom_prenoms }}</span></p>
                <p>Adresse <span style="font-weight:bold">{{ $enlevement->fournisseur->adresse_geo }}</span></p>
                <p>Contact: <span style="font-weight:bold">{{ $enlevement->fournisseur->contact1 }}</span></p>
            </div> --}}

            <div class="info-box" style="border: 1px solid #000; padding: 10px; margin-bottom: 20px;  ">
                <p>Détail livreur</p>
                <p>Nom prénom : <span style="font-weight:bold">{{ $bon->livraison->livreur->user->nom_prenoms }}</span> </p>
                <p>matricule du véhicule <span style="font-weight:bold">{{ $bon->matricule_vehicule }}</span> </p>
                {{-- <p>Télécopie</p>
                <p>Lieu de livraison:  <span style="font-weight:bold">{{$enlevement->livraison->AdresseLivraison->complement_adresse}}</span></p> --}}
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
                        <td style="border: 1px solid #000; padding: 10px; text-align: left;">{{ $bon->produit->nom }}</td>
                        <td style="border: 1px solid #000; padding: 10px; text-align: left;">{{ $bon->qte }}</td>
                        <td style="border: 1px solid #000; padding: 10px; text-align: left;">{{ $produit->prix }} fcfa</td>
                        <td style="border: 1px solid #000; padding: 10px; text-align: left; background-color: yellow;">{{ $produit->prix * $bon->qte }} fcfa</td>
                    </tr>
                    {{-- <tr class="total-row" style="">
                        <td colspan="3" style="border: 1px solid #000; padding: 10px; text-align: left;">Total</td>
                        <td  style="border: 1px solid #000; padding: 10px; text-align: left;">fcfa</td>
                    </tr> --}}
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


