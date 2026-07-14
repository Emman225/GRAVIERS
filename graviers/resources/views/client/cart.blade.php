<div class="table-responsive shopping-summery">
    <table id="table" class="table table-wishlist">
        <thead>
            <tr class="main-heading">
                <th class="custome-checkbox start pl-30">
                    {{-- <input class="form-check-input" type="checkbox" name="checkbox"
                        id="exampleCheckbox11" value=""> --}}
                    {{-- <label class="form-check-label" for="exampleCheckbox11"></label> --}}
                </th>
                <th scope="col" colspan="2">Produits</th>
                <th scope="col">Prix unitaire</th>
                <th scope="col">Quantité</th>
                <th scope="col">Sous-total</th>
                <th scope="col" class="end">Supprimer</th>
            </tr>
        </thead>
        <tbody>
            @php
                $total = 0;
                $i = 0;
            @endphp
            <form method="POST" id="formulaire" action="{{route('client.update.produit')}}" >
                @csrf
                @foreach (Cart::content() as $produit)
                    {{-- @dd(session('errorMontant'.$i)) --}}
                    {{-- @php $image = ' @endphp --}}
                    <tr class="pt-30">
                        <td class="custome-checkbox pl-30">
                            {{-- <input class="form-check-input" type="checkbox" name="checkbox"
                                id="exampleCheckbox1" value=""> --}}
                            {{-- <label class="form-check-label" for="exampleCheckbox1"></label> --}}
                        </td>
                        <td class="image product-thumbnail pt-40"><img
                                src="/storage/{{ $produit->options->image }}" alt="#"></td>
                        <td class="product-des product-name">
                            <h6 class="mb-5"><a class="product-name mb-10 text-heading"
                                    href="shop-product-right.html"> {{ $produit->name }} </a></h6>
                            <div class="product-rate-cover">
                                <div class="product-rate d-inline-block">
                                    <div class="product-rating"
                                        style="width: {{ $produit->model->meilleur_note }}%">
                                    </div>
                                </div>
                                <span class="font-small ml-5 text-muted">
                                    ({{ round(($produit->model->meilleur_note * 5) / 100, 1) }})
                                </span>
                            </div>
                        </td>
                        <td class="price" data-title="Prix">
                            <div class=" mr-15">
                                <div class="detail-qty">

                                    {{ number_format($produit->price, 0, '', ' ') }} fcfa /
                                    {{ $produit->model->unite }}


                                </div>
                            </div>
                            {{-- @if (session('errorMontant' . $i)) --}}

                            {{-- @endif --}}
                            {{-- <h4 class="text-body"> {{  }} </h4> --}}
                        </td>
                        <td class="text-center detail-info" data-title="Quantité">
                            <div class="detail-extralink mr-15">
                                <div class="detail-qty border radius">
                                    <a href="#" class="qty-down"><i
                                            class="fi-rs-angle-small-down"></i></a>
                                    <input type="text" name="qte[]" class="qty-val"
                                        value="{{ $produit->qty }}" min="1">
                                    <a href="#" class="qty-up"><i
                                            class="fi-rs-angle-small-up"></i></a>
                                </div>
                            </div>
                        </td>
                        <td class="price" data-title="Sous-total">
                            <div class="detail-extralink mr-15">
                                <div class="radius w-100 border-bleu">

                                    <input type="text" id="montant"
                                        min="{{ $produit->price }}" name="montant[]"
                                        class="qty-val"
                                        value=" {{ $produit->price * $produit->qty }}"
                                        min="1">

                                </div>
                            </div>
                            <h4 class="text-brand"> fcfa
                            </h4>
                        </td>
                        @php $total += $produit->price * $produit->qty @endphp
                        <td class="action text-center" data-title="Supprimer"><a
                                onclick="return confirm('Voulez vous supprimer ce produit?')"
                                href="{{ route('client.supprimer.produit', $produit->rowId) }}"
                                class="text-body"><i class="fi-rs-trash"></i></a></td>
                    </tr>
                    <input type="hidden" name="rowId[]" value="{{ $produit->rowId }}">
                    @php
                        $i++;
                    @endphp
                @endforeach

        </tbody>
        {{-- @dd() --}}
    </table>
</div>
