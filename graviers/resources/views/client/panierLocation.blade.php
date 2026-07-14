{{-- @if (isset($reduction))
    {{dd($reduction->taux_reduction)}}
@endif --}}

{{-- @dd(session()) --}}

@extends('client.main')
@section('title', 'Mon panier')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info conatiner.fluid text-center" id="notify">
            {{ session('ok') }}
        </div>
    @endif
    @if (session('info'))
        <div class="alert alert-info conatiner.fluid text-center" id="notify">
            {{ session('info') }}
        </div>
    @endif
    @if (session('success'))
        <div class="alert alert-success conatiner.fluid text-center" id="notify">
            {{ session('success') }}
        </div>
    @endif
    <div class="alert alert-info  text-center modifie coller-en-haut mt-5" style="display: none;" id="notify">
        <span>Panier mis a jour</span>
    </div>
    <main class="main">

        @include('client.navMobile')
        <div class="container mb-80 mt-50">
            <div class="row">
                <div class="col-lg-8 mb-40">
                    {{-- @dd(Cart::content()->first()->rowId) --}}
                    <h1 class="heading-2 mb-10">Votre panier</h1>
                    <div class="d-flex justify-content-between">
                        @if (Cart::count() > 0)
                            <h6 class="text-body">Il y a <span class="text-brand"> {{ Cart::count() }} </span>
                                article{{ Cart::count() > 1 ? 's' : '' }} dans votre panier</h6>
                            <h6 class="text-body"><a href="{{ route('client.nettoyer') }}" class="text-muted"
                                    onclick="return confirm('Voulez-vous vraiment vider le panier??')"><i
                                        class="fi-rs-trash mr-5"></i>Nettoyer le panier</a></h6>
                        @endif
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-9 contenuPanier">

                    @if (Cart::count() > 0)
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
                                        <th scope="col">Prix par jour</th>
                                        <th scope="col">Qté</th>
                                        <th scope="col">Sous-total</th>
                                        <th scope="col" class="end">Supprimer</th>
                                    </tr>
                                </thead>
                                @php
                                    // $total = 0;
                                    $i = 0;
                                @endphp
                                <form method="POST"  action="{{ route('client.update.produit') }}">
                                    @csrf
                                    <tbody id="listProduit">
                                        @foreach (Cart::content() as $produit)
                                        {{-- @dd($produit) --}}
                                        @php $type_affaire = $produit->options->type_affaire @endphp
                                            <tr class="pt-30"  data-rowid="{{ $produit->rowId }}">
                                                <td class="custome-checkbox pl-30">
                                                </td>

                                                <td class="image product-thumbnail pt-40">
                                                    <img src="/storage/{{ $produit->options->image }}" alt="#">
                                                </td>

                                                <td class="product-des product-name">
                                                    <h6 class="mb-5"><a class="product-name mb-10 text-heading" href="{{route('client.produit.info',$produit->id)}}"> {{ $produit->name }} </a></h6>
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
                                                            {{ $produit->model->UniteProduit->abreviation }}


                                                        </div>
                                                    </div>

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
                                                                value="{{ $produit->price * $produit->qty }}"
                                                                min="1">

                                                        </div>
                                                    </div>
                                                    <h4 class="text-brand"> fcfa
                                                    </h4>
                                                </td>
                                                        {{-- @php $total += $produit->model->prix_moyen * $produit->qty @endphp --}}
                                                <td class="action text-center" data-title="Supprimer">
                                                    <a onclick="return confirm('Voulez vous supprimer ce produit?')" href="{{ route('client.supprimer.produit', $produit->rowId) }}" class="text-body">
                                                        <i class="fi-rs-trash"></i>
                                                    </a>
                                                </td>
                                                <input type="hidden" name="rowId[]" value="{{ $produit->rowId }}">
                                            </tr>
                                            @php
                                                $i++;
                                                @endphp
                                        @endforeach

                                    </tbody>
                                    {{-- @dd() --}}
                                </table>

                                <div class="divider-2 mb-30">


                                </div>
                                <div class="cart-action d-flex justify-content-between">
                                    <a class="btn" href="{{ route('client.index') }}"><i class="fi-rs-arrow-left mr-10"></i>Continuer les achats</a>
                                    {{-- <button class="btn" formaction="{{ route('client.testPanier') }}"><i class="fi-rs-arrow-left mr-10"></i>testPanier</button> --}}
                                    {{-- <button type="submit" id="maj"  class="btn  mr-10 mb-sm-15"><i
                                            class="fi-rs-refresh mr-10"></i>Appliquer</button> --}}
                                </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-3">
                    <div class="border p-md-4 cart-totals ml-30 mb-30">
                        <div class="table-responsive">
                            <h5 class="text-brand mb-20">Votre commande</h5>
                            <table class="table no-border col-12">
                                <tbody>
                                    <tr>
                                        <td >
                                            <h6 class="text-muted">Montant HT <small>(par jour)</small></h6>
                                        </td>
                                        <td></td>
                                        <td class="cart_total_amount">
                                           <h6 class="text-brand text-end"> <span id="montant_total"> {{ number_format($totalCommande, 0, '', ' ') }}</span> fcfa</h6>
                                        </td>
                                    </tr>
                                    {{-- @if($client->applique_tva) --}}
                                        <tr>
                                            <td class="cart_total_label">
                                                <h6 class="text-muted">TVA <small>(par jour)</small></h6>
                                            </td>
                                            <td></td>
                                            <td class="cart_total_amount">

                                                <h6 class="text-brand text-end"> <span
                                                        id="montant_tva">{{ number_format($totalCommande*$tva, 0, '', ' ') }}</span> fcfa <br>
                                                </h6>
                                            </td>
                                        </tr>
                                    {{-- @endif --}}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th class="cart_total_label">
                                            <h6 class="text-muted text-start">Montant TTC <small>(par jour)</small></h6>
                                        </th>
                                        <th></th>
                                        <th class="cart_total_amount">

                                            <h6 class="text-brand text-end">
                                                <span id="montant_ttc">
                                                    {{ number_format($totalCommande+($totalCommande*$tva), 0, '', ' ') }}
                                                </span> fcfa <br>
                                            </h6>
                                        </th>
                                    </tr>
                                </tfoot>

                                    {{-- <tr>
                                        <td scope="col" colspan="2">
                                            <div class="divider-2 mt-10 mb-10"></div>
                                        </td>
                                    </tr> --}}

                            </table>
                            <p class="text-muted small mb-0">
                                <i class="material-icons md-info" style="font-size:14px;vertical-align:middle;"></i>
                                Montants indiqués <strong>par jour</strong>. Le total dépendra du nombre de jours choisi à l'étape suivante (choix des dates).
                            </p>
                        </div>

                        @if ($type_affaire == 'VENTE')
                            <a href="{{ route('client.commandeAdresse') }}" class="btn mb-20 w-100">Ajouter le mode de livraison</a>

                        @else
                            <a href="{{ route('client.locationAdresse') }}" onclick="getLocationDate()" class="btn mb-20 w-100">Valider la location</a>
                        @endif

                    </div>
                </div>
                @else
                    <h4>Vous n'avez selectionné aucun produit</h4>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection

@section('jspart')
    <script>
        var map = L.map('map').setView([51.505, -0.09], 13);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        L.marker([51.5, -0.09]).addTo(map)
            .bindPopup('A pretty CSS popup.<br> Easily customizable.')
            .openPopup();
    </script>

    <script>
    $(document).ready(function () {

        function updateByTotal(rowId, qte, prixUnitaire, montant) {
                console.log("RowId:", rowId, "Qte:", qte, "Prix Unitaire:", prixUnitaire, "Montant:", montant);

             $.ajax({
                url: '{{ route('panier.update.all') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    rowId: rowId,
                    qte: qte,
                    prixUnitaire: prixUnitaire,
                    montant: montant
                },
                success: function (response) {
                    if (response.success) {
                        console.log('prixUnitaire:');
                        console.log("Response:", response);

                        if (response.qte < 0.1) {
                            alert('La quantité doit être supérieure à 0.');
                            return;
                        }

                        const tr = $('tr[data-rowid="' + response.rowId + '"]');

                        // Mettre à jour les champs qte[] et montant[]
                        tr.find('input[name="qte[]"]').val(response.qte);
                        tr.find('input[name="montant[]"]').val(response.subtotal);
                        $('#montant_total').text(response.total);
                        $('#montant_tva').text(response.tva);
                        $('#montant_ttc').text(response.ttc);
                    }
                },
                error: function () {
                    alert('Une erreur est survenue lors de la mise à jour du total.');
                }
            });


        }

        const debounceTimers = {}; // Dictionnaire de timers par champ

        $(document).on('keyup', 'input[name="montant[]"]', function () {
            const inputMontant = $(this);
            const inputId = inputMontant.closest('tr').data('rowid');

            clearTimeout(debounceTimers[inputId]);

            debounceTimers[inputId] = setTimeout(function () {
                const tr = inputMontant.closest('tr');
                const rowId = tr.data('rowid');

                // const montant = parseInt(inputMontant.val());
                const montant = inputMontant.val();
                console.log('montant:', montant)
                const qte = parseFloat(tr.find('input[name="qte[]"]').val()) || 0;


                //updateByTotal(rowId, qte, montant);

                const prixText = tr.find('td.price .detail-qty').text().trim();
                const matchPrix = prixText.match(/(\d+([.,]?\d+)?)/);
                const prixUnitaire = matchPrix ? parseFloat(matchPrix[1].replace(',', '.')) : 0;
                updateByTotal(rowId, qte, prixUnitaire, montant);
            }, 500); // Délai de 500 ms
        });

        /**@argument
         * @description Cette fonction est appelée lors de la validation de la location
         * Elle permet de récupérer les dates de début et de fin de location
         * et de les stocker dans des variables pour les utiliser dans la requête AJAX
         */
        function updateTotals(row, newQty) {
            let rowId = row.data('rowid');
            console.log(rowId, newQty);


            $.ajax({
                url: '{{ route('panier.update.quantite') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    rowId: rowId,
                    qty: newQty
                },
                success: function (response) {
                    if (response.success) {

                        console.log(response);
                        row.find('input[id=montant]').val(response.subtotal);
                        $('#montant_total').text(response.total);
                        $('#montant_tva').text(response.tva);
                        $('#montant_ttc').text(response.ttc);
                    }
                },
                error: function () {
                    alert('Une erreur est survenue.');
                }
            });
        }

        $('.qty-up').click(function (e) {
            e.preventDefault();


            let row = $(this).closest('tr');
            let input = row.find('input[name="qte[]"]');
            let newQty = parseInt(input.val()) + 1;

            input.val(newQty);
            updateTotals(row, newQty);
        });

        $('.qty-down').click(function (e) {
            e.preventDefault();
            let row = $(this).closest('tr');
            let input = row.find('input[name="qte[]"]');
            let currentQty = parseInt(input.val());

            if (currentQty > 1) {

                input.val(newQty);
                updateTotals(row, newQty);
            }
        });

        $('input[name="qte[]"]').on('keyup', function () {
            let row = $(this).closest('tr');
            let newQty = parseInt($(this).val());
            if (newQty > 0) {
                updateTotals(row, newQty);
            } else {
                $(this).val(0.1);
                updateTotals(row, 0.1);
            }
        });

        // $(document).on('keyup', 'input[name="qte[]"]', function () {

        //     const qte = $(this);
        //     const inputId = qte.closest('tr').data('rowid');

        //     clearTimeout(debounceTimers[inputId]);

        //     debounceTimers[inputId] = setTimeout(function () {
        //         const tr = qte.closest('tr');
        //         const rowId = tr.data('rowid');

        //         // const montant = parseInt(qte.val());
        //         let qteFinal = (qte.val());

        //         if (isNaN(qteFinal) || qteFinal < 0.1) {
        //             qteFinal = 1;
        //         }

        //         console.log('qteFinal:', qteFinal);

        //         const montant = parseFloat(tr.find('input[name="montant[]"]').val()) || 0;

        //         updateByTotal(rowId, qte, montant);

        //         const prixText = tr.find('td.price .detail-qty').text().trim();
        //         const matchPrix = prixText.match(/(\d+([.,]?\d+)?)/);
        //         const prixUnitaire = matchPrix ? parseFloat(matchPrix[1].replace(',', '.')) : 0;

        //         updateByTotal(rowId, qteFinal, prixUnitaire, montant);
        //     }, 500); // Délai de 500 ms
        // });
        // $('input[name="qte[]"]').on('change', function () {
        //     let row = $(this).closest('tr');
        //     let newQty = parseInt($(this).val());


        //     if (newQty > 0) {
        //         updateTotals(row, newQty);
        //     } else {
        //         $(this).val(1);
        //         updateTotals(row, 1);
        //     }
        // });
    });
</script>
@endsection
