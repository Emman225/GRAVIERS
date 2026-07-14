{{-- @dd(session('type')) --}}

@php
    $i = 0;
@endphp

@extends('client.main')
@section('title', 'Mode de paiement')
@section('content')
    <main class="main">

        {{-- map --}}

        {{-- map --}}
        {{-- @dd(Cart::content()) --}}
        <div class="container mb-80 mt-50">

            {{-- @dd(session()) --}}
            <div class="row">
                <div class="col-lg-7">
                    <div class="row mb-50">

                        <div class="col-lg-6">

                        </div>
                    </div>
                    <div class="row">

                        <h4 class="mb-30">Enregistrement de bon de commande interne </h4>
                        <form method="post">
                            @csrf
                            <div class="row shipping_calculator">
                                <div class="form-group col-lg-6">
                                    {{-- <div class="custom_select">
                                        <select required style="border: solid 1px grey" class="form-control" name="mode">
                                            <option value="">Mode de paiement...</option>
                                            @foreach ($modes as $mode)
                                                <option value="{{ $mode->id }}">{{ $mode->description }}</option>
                                            @endforeach
                                        </select>
                                    </div> --}}
                                    <input type="number" placeholder="Entrez un numero de bon commande" name="bonCommande" class="form-control "  style="border: 1px solid black">
                                    <div class="container mt-2">
                                        <label for="" class="form-label">Seletionné votre fichier de bon de commande</label>
                                        <input type="file" name="fichierBon" class="form-control" style="border: 1px solid black">
                                    </div>
                                </div>
                            </div>

                            @if (session('type') == 'commande')
                                <button type="submit" style="display: none" formaction="{{ route('client.recapCommande') }}"
                                class="btn btn-fill-out btn-block mt-30" id="recap">Recapitulatif de la commande <i
                                class="fi-rs-check ml-15"></i></button>
                            @endif


                            @if (session('type') == 'devis')
                                <button type="submit" style="display: none" formaction="{{ route('client.recapCommandeVenantDunDevis',$devis) }}"
                                    class="btn btn-fill-out btn-block mt-30" id="recapDevis">Recapitulatif de la commande venant d'un devis  <i
                                        class="fi-rs-check ml-15"></i></button>
                            @endif


                            @if (session('type') == 'location')
                                <button type="submit" style="display: none" formaction="{{ route('client.recapLocation') }}"
                                    class="btn btn-fill-out btn-block mt-30" id="recapLocation">Recapitulatif de la commande venant d'un devis  <i
                                        class="fi-rs-check ml-15"></i></button>
                            @endif

                        </form>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="border p-40 cart-totals ml-30 mb-50">
                        <h4>Votre commande</h4>
                        @if(session('type') !== 'location')
                            <div class="d-flex align-items-end justify-content-between mb-30">
                                <p class="">
                                    <div class="container ">

                                        <div id="montantReductionParPoint"> @if (session('point_reduc'))reduction par point: <span class="fw-bold text-danger">{{number_format($montantPoint,0,'',' ')}}fcfa</span> @endif</div>


                                        <div id="montantReductionParCode">@if (session('reduction_id'))reduction code promo: <span class="fw-bold text-danger">{{number_format($montantPromo,0,'',' ')}}fcfa</span> @endif</div>

                                    </div>
                                    <div class="container">
                                        @if (session('point_reduc') || session('reduction_id'))
                                            <h1 class="fw-bold barre">{{number_format(Cart::total(),0,'',' ') }}fcfa</h1>
                                        @endif <br>
                                        <span class="h3 fw-bold">{{ number_format($total, 0, '', ' ') }} fcfa</span>
                                    </div>
                                </p>
                            </div>
                        @else
                            <div class="d-flex align-items-end justify-content-between mb-30">
                                <p class="">
                                    <div class="container ">

                                        <div id="montantReductionParPoint"> @if (session('point_reduc'))reduction par point: <span class="fw-bold text-danger">{{number_format($montantPoint,0,'',' ')}}fcfa</span> @endif</div>


                                        <div id="montantReductionParCode">@if (session('reduction_id'))reduction code promo: <span class="fw-bold text-danger">{{number_format($montantPromo,0,'',' ')}}fcfa</span> @endif</div>

                                    </div>
                                    <div class="container">
                                        @if (session('point_reduc') || session('reduction_id'))
                                            <h1 class="fw-bold barre">{{number_format(session('totalLocation'),0,'',' ') }}fcfa</h1>
                                        @endif <br>
                                        <span class="h3 fw-bold">{{ number_format($total, 0, '', ' ') }} fcfa</span>
                                    </div>
                                </p>
                            </div>
                        @endif
                        <div class="divider-2 mb-30"></div>

                        {{-- Panier DEVIS --}}
                        @if (session('type') == 'devis')
                        {{-- @dd('devis') --}}
                            <div class="table-responsive order_table checkout">
                                <table class="table no-border">
                                    <tbody>
                                        @foreach ($devis->detailDevis as $detail)
                                            <tr>
                                                <td class="image product-thumbnail">

                                                    @foreach ($detail->produit->image as $image)
                                                        <img
                                                            src="storage/{{ $image->image }}" alt="#"></td>

                                                    @endforeach
                                                <td>
                                                    <h6 class="w-160 mb-5"><a href="shop-product-full.html"
                                                            class="text-heading">{{ $detail->produit->nom }}</a></h6></span>
                                                    <div class="product-rate-cover">
                                                        <div class="product-rate d-inline-block">
                                                            <div class="product-rating"
                                                                style="width :{{ $detail->produit->meilleur_note }}%">
                                                            </div>
                                                        </div>
                                                        <span class="font-small ml-5 text-muted">
                                                            ({{ round(($detail->produit->meilleur_note * 5) / 100, 1) }})</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <h6 class="text-muted pl-20 pr-20">x {{ $detail->qte }} </h6>
                                                </td>
                                                <td>
                                                    <h4 class="text-brand">
                                                        @if(isset($prixPerso[$detail->produit->id]))
                                                            {{ number_format($prixPerso[$detail->produit->id], 0, '', ' ') }} fcfa
                                                        @else
                                                            {{ number_format($detail->produit->prix_moyen, 0, '', ' ') }} fcfa
                                                        @endif
                                                    </h4>
                                                </td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                            </div>
                            <label for="recapDevis" class="btn btn-fill-out btn-block mt-30">Recapitulatif de la commande<i class="fi-rs-check ml-15"></i></label>
                        @endif
                        {{-- PANIER COMMANDE --}}
                        @if (session('type') == 'commande')
                        {{-- @dd('commande') --}}
                            <div class="table-responsive order_table checkout">
                                <table class="table no-border">
                                    <tbody>
                                        @foreach (Cart::content() as $produit)
                                            <tr>
                                                <td class="image product-thumbnail"><img
                                                        src="storage/{{ $produit->options->image }}" alt="#"></td>
                                                <td>
                                                    <h6 class="w-160 mb-5"><a href="shop-product-full.html"
                                                            class="text-heading">{{ $produit->name }}</a></h6></span>
                                                    <div class="product-rate-cover">
                                                        <div class="product-rate d-inline-block">
                                                            <div class="product-rating"
                                                                style="width :{{ $produit->options->note }}%">
                                                            </div>
                                                        </div>
                                                        <span class="font-small ml-5 text-muted">
                                                            ({{ round(($produit->options->note * 5) / 100, 1) }})</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }} </h6>
                                                </td>
                                                <td>
                                                    <h4 class="text-brand"> {{ number_format($produit->price, 0, '', ' ') }} fcfa
                                                    </h4>
                                                </td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                                <label for="recap" class="btn btn-fill-out btn-block mt-30">Recapitulatif de la commande<i class="fi-rs-check ml-15"></i></label>
                            </div>
                        @endif

                        {{-- PANIER LOCATION --}}
                        @if (session('type') == 'location')
                        {{-- @dd('location') --}}
                            <div class="table-responsive order_table checkout">
                                <table class="table no-border">
                                    <tbody>
                                        @foreach (Cart::content() as $produit)
                                            <tr>
                                                <td class="image product-thumbnail"><img
                                                        src="storage/{{ $produit->options->image }}" alt="#"></td>
                                                <td>
                                                    <h6 class="w-160 mb-5"><a href="shop-product-full.html"
                                                            class="text-heading">{{ $produit->name }}</a></h6></span>
                                                    <div class="product-rate-cover">
                                                        <div class="product-rate d-inline-block">
                                                            <div class="product-rating"
                                                                style="width :{{ $produit->options->note }}%">
                                                            </div>
                                                        </div>
                                                        <span class="font-small ml-5 text-muted">
                                                            ({{ round(($produit->options->note * 5) / 100, 1) }})</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }} </h6>
                                                </td>
                                                <td> <h6 class="text-muted pl-20 pr-20">{{session('nbre_jour')[$i]}}j</h6> </td>
                                                <td>
                                                    <h4 class="text-brand"> {{ number_format($produit->price, 0, '', ' ') }} fcfa
                                                    </h4>
                                                </td>
                                            </tr>
                                            @php
                                                $i++
                                            @endphp
                                        @endforeach

                                    </tbody>
                                </table>
                                <div class="container d-flex justify-between">
                                    <label for="recapLocation" class="btn btn-fill-out btn-block mt-30 col-5 w-100">Recapitulatif de la location<i class="fi-rs-check ml-15"></i></label>
                                    {{-- <label for="recapLocation" class="btn btn-fill-out btn-block mt- ms-10 col-5">Enregistrer un devis<i class="fi-rs-check ml-15"></i></label> --}}
                                </div>
                            </div>
                        @endif

                    </div>

                </div>
            </div>
        </div>
    </main>
@endsection
@section('jspart')
    <script type="text/javascript">
        $(function() {
            $('#produits').select();
        });
    </script>
@endsection
