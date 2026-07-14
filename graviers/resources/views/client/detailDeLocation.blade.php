@php
    use Illuminate\Support\Carbon;
@endphp

@extends('client.main')
@section('title','Détail de la location')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info conatiner.fluid text-center" id="notify">
            {{ session('ok') }}
        </div>
    @endif
    @if(session('errorQte'))
    <div class="alert alert-danger text-center" id="notify"> {{session('errorQte')}} </div>
@endif
    @if(session('livree'))
    <div class="alert alert-success text-center" id="notify"> {{session('livree')}} </div>
@endif
    <main class="main">
        @include('client.navMobile')
        <div class="container mb-80 mt-50">
            <div class="row">
                <div class="col-lg-8 mb-40">
                    <h1 class="heading-2 mb-10">Votre commande</h1>
                    <div class="d-flex justify-content-between">
                    </div>
                </div>
            </div>
            {{-- <a href="{{route('client.modifierAdresseLivraison',$commande)}}" class="btn btn-primary mb-10">changer d'adresse de livraison</a> --}}
            <div class="row">
                <div class="col-lg-11">


                        <div class="table-responsive shopping-summery">
                            <table class="table table-wishlist">
                                <thead>
                                    <tr class="main-heading">
                                        <th class="custome-checkbox start pl-30">

                                        </th>
                                        <th scope="col" colspan="2">Produits</th>
                                        <th scope="col">Prix/jour</th>
                                        <th scope="col">Quantité</th>
                                        <th scope="col">Delai de location</th>

                                        <th scope="col" class="text-center">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $total = 0 @endphp
                                    <form action="" method="post">
                                        @csrf
                                        @foreach ($location->detailLocation as $detail)
                                        <input type="hidden" name="">

                                                <tr class="pt-30">

                                                    <td class="custome-checkbox pl-30">

                                                    </td>

                                                    <td class="image product-thumbnail pt-40">
                                                        @foreach($detail->produit->image as $image)
                                                            <img src="/storage/{{$image->image}}" alt="#">
                                                        @endforeach
                                                    </td>

                                                    <td class="product-des product-name">
                                                        <h6 class="mb-5"><a class="product-name mb-10 text-heading"
                                                                href="shop-product-right.html"> {{ $detail->produit->nom }} </a></h6>

                                                                {{-- <small class="text-muted"> {{$detail->livraisons->count()}} livraison{{($detail->livraisons->count() >1) ? 's' :'' }} prévu</small> --}}
                                                        <div class="product-rate-cover">
                                                            <div class="product-rate d-inline-block">
                                                                <div class="product-rating"
                                                                    style="width: {{ $detail->produit->meilleur_note }}%">
                                                                </div>
                                                            </div>
                                                            <span class="font-small ml-5 text-muted">
                                                                ({{ round(($detail->produit->meilleur_note * 5) / 100, 1) }})</span>
                                                        </div>
                                                    </td>

                                                    <td class="price" data-title="Prix">
                                                        <h4 class="text-body"> {{ number_format($detail->prix,'0','',' ') }} fcfa</h4>
                                                    </td>

                                                    <td class="text-center detail-info" data-title="Quantité">
                                                        <div class="detail-extralink mr-15">
                                                            <div class="detail-qty border radius">
                                                                <p> {{$detail->qte}} </p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="price" data-title="Sous-total">
                                                        <span class=""> Du {{Carbon::parse($detail->debut)->format('d-m-Y')}} au {{Carbon::parse($detail->fin)->format('d-m-Y')}} </span> <br> ({{$detail->nombre_jour}} jours) </span>

                                                    </td>




                                                    <td class="action text-center" data-title="Supprimer">

                                                        {{number_format($detail->prix * $detail->qte * $detail->nombre_jour,'0','',' ')}} fcfa

                                                    </td>
                                                </tr>

                                        @endforeach
                                </tbody>
                                <tfoot>
                                    <th>
                                        <td colspan="5" class="text-right"> <h2>Total</h2></td>
                                        <td class="text-center"><h2>{{number_format($location->montant_total,'0','',' ')}}fcfa</h2> </td>
                                    </th>
                                </tfoot>

                            </table>
                        </div>

                    <div class="divider-2 mb-30"></div>
                    <div class="cart-action d-flex justify-content-between">
                        <a class="btn" href="{{ route('client.monCompte') }}">Retour</a>
                    </div>
                    </form>
                </div>

            </div>
        </div>
    </main>
    @endsection
