@php
    // dd(session('type'));

    // dd(session('type'));

    // if(session('type') == 'devis'){
    //     $type_affaire = session('type_affaire');
    // }else{
    //     foreach(Cart::content() as $produit){

    //         $type_affaire = $produit->options->type_affaire;
    //         break;
    //     }
    // }


    $total = session('type') == 'commande' ? Cart::total() : session('totalLocation');
    $client = Auth::user()->client;
    $tva = $client->tva($client);
    $i=0;


    // dd($type_affaire);

    // dd($type_affaire);

@endphp

@extends('client.main')
@section('title', 'Page de reference bancaire')
@section('content')
    <main class="main">

        {{-- map --}}

        {{-- map --}}
        {{-- @dd(Cart::content()) --}}
        <div class="container mb-80 mt-50">
            {{-- <div class="row">
                <div class="col-lg-8 mb-40">
                    <h1 class="heading-2 mb-10">Checkout</h1>
                    <div class="d-flex justify-content-between">
                        <h6 class="text-body">Il y a <span class="text-brand"> {{ Cart::count() }} </span> articles dans
                            votre panier</h6>
                    </div>
                </div>
            </div> --}}
            <div class="row">
                <div class="col-lg-7">

                    <div class="row">
                        <h4 class="mb-30">Entrez les référence du virement</h4>
                        <p> </p>
                        <form method="post" action="" enctype="multipart/form-data">
                            @csrf
                            <div class="row shipping_calculator d-flex ">
                                <div class="col-6">
                                    <span>Reference</span>
                                    <input type="text" style="border: solid 1px grey" required name="reference"
                                        class=" mb-5 mt-5 form-control">
                                    @error('reference')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <span>Numéro de compte</span>
                                    <input type="text" style="border: solid 1px grey" required name="num_compte"
                                        class=" mb-5 mt-5 form-control">
                                    @error('num_compte')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="row shipping_calculator d-flex ">
                                <div class="col-6">
                                    <span>Banque</span>
                                    <input type="text" style="border: solid 1px grey" required name="banque"
                                        class=" mb-5 mt-5 form-control">
                                    @error('banque')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <span>Date de l'opération bancaire</span>
                                    <input type="date" style="border: solid 1px grey" max="{{now()->format('Y-m-d')}}" required name="date_operation"
                                        class=" mb-5 mt-5 form-control">
                                    @error('date_operation')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row shipping_calculator d-flex ">
                                <div class="col-12">
                                    <span>Document du paiement</span>
                                    <input type="file" style="border: solid 1px grey" required name="fichier"
                                        class=" mb-5 mt-5 form-control">
                                    @error('fichier')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="row shipping_calculator d-flex ">
                                <div class="col-12">
                                    <span>Description</span>
                                    <textarea name="note_supp" id="description" class="form-control" rows="3"></textarea>
                                    @error('note_supp')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <hr>


                            <button type="submit" style="display: block" id="modePaiement"
                                class="btn btn-fill-out btn-block mt-30">
                                Valider
                                <i class="fi-rs-money ml-15"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="border p-40 cart-totals ml-30 mb-50">
                        <h4>Votre commande</h4>
                        <div class="d-flex align-items-end justify-content-between mb-30">
                            <p class="">
                            <div class="container ">

                                {{-- <div id="montantReductionParPoint"> @if (session('point_reduc'))reduction par point: <span class="fw-bold text-danger">{{number_format($montantPoint,0,'',' ')}}fcfa</span> @endif</div>


                                    <div id="montantReductionParCode">@if (session('reduction_id'))reduction code promo: <span class="fw-bold text-danger">{{number_format($montantPromo,0,'',' ')}}fcfa</span> @endif</div> --}}

                            </div>
                            {{-- <div class="container">
                                    @if (session('point_reduc') || session('reduction_id'))
                                        <h1 class="fw-bold barre">{{number_format(Cart::total(),0,'',' ') }}fcfa</h1>
                                    @endif <br>
                                    <span class="h3 fw-bold">{{ number_format($total, 0, '', ' ') }} fcfa</span>
                                </div> --}}
                            </p>
                        </div>
                        <div class="divider-2 mb-30"></div>
                        @if (session('type') == 'commande')
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
                                                            ({{ round(($produit->options->note * 5) / 100, 1) }})
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }} </h6>
                                                </td>
                                                <td>
                                                    <h4 class="text-brand"> {{ number_format($produit->price, 0, '', ' ') }}
                                                        fcfa
                                                    </h4>
                                                </td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                                <table class="table no-border col-12">
                                    <tbody>
                                        <tr>
                                            <td>
                                                <h6 class="text-muted">Montant HT</h6>
                                            </td>
                                            <td></td>
                                            <td class="cart_total_amount">

                                                <h6 class="text-brand text-end">{{ number_format($total, 0, '', ' ') }} fcfa
                                                </h6>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="cart_total_label">
                                                <h6 class="text-muted">TVA</h6>
                                            </td>
                                            <td></td>
                                            <td class="cart_total_amount">

                                                <h6 class="text-brand text-end"> <span
                                                        id="montant_total">{{ number_format($total * $tva, 0, '', ' ') }}</span>
                                                    fcfa <br>
                                                </h6>
                                            </td>
                                        </tr>
                                        @if (session('0')['ville'] != null)
                                            <tr>
                                                <td class="cart_total_label">
                                                    <h6 class="text-muted">Cout livraison</h6>
                                                </td>
                                                <td></td>
                                                <td class="cart_total_amount">

                                                    <h6 class="text-brand text-end"> <span id="montant_total">
                                                            ({{ session('0')['km'] }} km)
                                                            {{ number_format(session('0')['cout_livraison'], 0, '', ' ') }}</span>
                                                        fcfa <br>
                                                    </h6>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="cart_total_label">
                                                <h6 class="text-muted text-start">Montant TTC</h6>
                                            </th>
                                            <th></th>
                                            <th class="cart_total_amount">

                                                <h6 class="text-brand text-end"> <span
                                                        id="montant_total">{{ number_format($total + $total * $tva + session('0')['cout_livraison'], 0, '', ' ') }}</span>
                                                    fcfa <br>
                                                </h6>
                                            </th>
                                        </tr>
                                        <tr id="mpRemise">
                                            <th class="cart_total_label">
                                                <h6 class="laRemise">Montant remise</h6>
                                            </th>
                                            <th></th>
                                            <th class="cart_total_amount">

                                                <h6 class="text-brand text-end"> <span id="laRemise"></span>
                                                    fcfa <br>
                                                </h6>
                                            </th>
                                        </tr>
                                        <tr id="mpMontantTotal">
                                            <th class="cart_total_label">
                                                <h6 class="">Montant Total</h6>
                                            </th>
                                            <th></th>
                                            <th class="cart_total_amount">

                                                <h6 class="text-brand text-end"> <span
                                                        id="leMontantTotal">{{ number_format($total + $total * $tva + session('0')['cout_livraison'], 0, '', ' ') }}</span>
                                                    fcfa <br>
                                                </h6>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif

                        @if (session('type') == 'location')
                            {{-- @dd('location') --}}
                            <div class="table-responsive order_table checkout">
                                <table class="table no-border">
                                    <tbody>
                                        @foreach (Cart::content() as  $produit)

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
                                                            ({{ round(($produit->options->note * 5) / 100, 1) }})
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }} </h6>
                                                </td>
                                                <td>
                                                    <h6 class="text-muted pl-20 pr-20">{{ session('nbre_jour')[$i] }}j</h6>
                                                </td>
                                                <td>
                                                    <h4 class="text-brand"> {{ number_format($produit->price, 0, '', ' ') }}
                                                        fcfa
                                                    </h4>
                                                </td>
                                            </tr>
                                            @php
                                                $i++;
                                            @endphp
                                        @endforeach

                                    </tbody>
                                </table>
                                <div class="container">
                                    <table class="table no-border col-12">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <h6 class="text-muted">Montant HT</h6>
                                                </td>
                                                <td></td>
                                                <td class="cart_total_amount">

                                                    <h6 class="text-brand text-end">{{ number_format($total, 0, '', ' ') }}
                                                        fcfa</h6>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="cart_total_label">
                                                    <h6 class="text-muted">TVA</h6>
                                                </td>
                                                <td></td>
                                                <td class="cart_total_amount">

                                                    <h6 class="text-brand text-end"> <span
                                                            id="montant_total">{{ number_format(session(0)['tva'], 0, '', ' ') }}</span>
                                                        fcfa <br>
                                                    </h6>
                                                </td>
                                            </tr>

                                            @if (session('0')['ville'] != null)
                                                <tr>
                                                    <td class="cart_total_label">
                                                        <h6 class="text-muted">Cout livraison</h6>
                                                    </td>
                                                    <td></td>
                                                    <td class="cart_total_amount">

                                                        <h6 class="text-brand text-end"> <span id="montant_total">
                                                                ({{ session('0')['km'] }} km)
                                                                {{ number_format(session('0')['cout_livraison'], 0, '', ' ') }}</span>
                                                            fcfa <br>
                                                        </h6>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th class="cart_total_label">
                                                    <h6 class="text-muted text-start">Montant TTC</h6>
                                                </th>
                                                <th></th>
                                                <th class="cart_total_amount">

                                                    <h6 class="text-brand text-end"> <span
                                                            id="montant_total">{{ number_format($total + $total * $tva + session('0')['cout_livraison'], 0, '', ' ') }}</span>
                                                        fcfa <br>
                                                    </h6>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="container ">
                                    {{-- <label for="recapLocation"
                                        class="btn btn-fill-out btn-block mt-30 col-5 w-100">Recapitulatif de la location<i
                                            class="fi-rs-check ml-15"></i></label> --}}
                                    {{-- <label for="recapDevisLocation" class="btn btn-fill-out btn-block mt-30 col-5 w-100">Enregistrer un devis<i class="fi-rs-check ml-15"></i></label> --}}
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

@endsection
