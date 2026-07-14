{{-- --{{$s = $fournisseur->produits->count() >1 ? 's'}} s : --}}

@extends('layout.main')
@section('contenu')
@section('title','Profile fournisseur')
    {{-- @dump($fournisseur) --}}


    <section class="content-main" style="z-index: -2">
        <div class="content-header">
            <a href="javascript:history.back()"><i class="material-icons md-arrow_back"></i> </a>
        </div>
        <div class="card mb-4">
            <div class="card-header bg-brand-2" style="height: 150px"></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl col-lg flex-grow-0" style="flex-basis: 230px">
                        <div class="img-thumbnail shadow w-100 bg-white position-relative text-center"
                            style="height: 190px; width: 200px; margin-top: -120px">
                            <img src="assets/imgs/brands/vendor-2.png" class="center-xy img-fluid" alt="Logo Brand" />
                        </div>
                    </div>

                    <!--  col.// -->
                    <div class="col-xl col-lg">
                        <h3> {{ $fournisseur->nom_prenoms }} </h3>
                        <p>{{ $fournisseur->user->login }}, {{ $fournisseur->user->email }} </p>
                    </div>
                    <!--  col.// -->
                    {{-- <div class="col-xl-4 text-md-end">
                                <select class="form-select w-auto d-inline-block">
                                    <option>Actions</option>
                                    <option>Disable shop</option>
                                    <option>Analyze</option>
                                    <option>Something</option>
                                </select>
                                <a href="#" class="btn btn-primary"> View live <i class="material-icons md-launch"></i> </a>
                            </div> --}}
                    <!--  col.// -->
                </div>
                <!-- card-body.// -->
                <hr class="my-4" />
                <div class="row g-4">
                    <div class="col-md-12 col-lg-4 col-xl-2">
                        <article class="box">
                            {{-- <p class="mb-0 text-muted">Total vendu:</p>
                            <h5 class="text-success"> {{ $enlevement }} </h5> --}}
                            <p class="mb-0 text-muted">Revenue:</p>
                            <h5 class="text-success mb-0">{{ $fournisseur->solde }} fcfa</h5>
                        </article>
                    </div>
                    <!--  col.// -->
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <h6>Contacts</h6>
                        <p>
                            {{ $fournisseur->contact1 }} <br />
                            {{ $fournisseur->contact2 }} <br />

                        </p>
                        <p>
                            <span class="fw-bold">
                                @foreach ($fournisseur->produits as $produit)
                                     {{ $produit->nom.': '.$produit->pivot->qte }} |
                                @endforeach
                            </span>
                        </p>
                    </div>
                    <!--  col.// -->
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <h6>Adresse</h6>
                        <p>
                            {{ $fournisseur->adresse_geo }} <br />

                        </p>
                    </div>
                    <!--  col.// -->
                    {{-- <div class="col-sm-6 col-xl-4 text-xl-end">
                                <map class="mapbox position-relative d-inline-block">
                                    <img src="" class="rounded2" height="120" alt="map" />
                                    <span class="map-pin" style="top: 50px; left: 100px"></span>
                                    <button class="btn btn-sm btn-brand position-absolute bottom-0 end-0 mb-15 mr-15 font-xs">Large</button>
                                </map>
                            </div> --}}
                    <!--  col.// -->
                </div>
                <!--  row.// -->
            </div>
            <!--  card-body.// -->
        </div>
        <!--  card.// -->
        <div class="card mb-4">
            <div class="card-body">
                <h3 class="card-title">Liste des produits livrés</h3>
                <div class="row">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover text-center">
                                <thead>
                                    <tr>
                                        <th class="text-center">Code commande</th>
                                        <th class="text-center">Produit</th>
                                        <th class="text-center" >Quantité</th>
                                        <th class="text-center">livreur</th>
                                        <th class="text-center">Date</th>

                                        {{-- <th class="text-end">Action</th> --}}
                                        {{-- <th class="text-end">Action</th> --}}
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- @php dd($enlevements) @endphp --}}
                                    @foreach ($enlevements as $enlevement)
                                        <tr>
                                            <td>{{ $enlevement->code_enleve}}</td>
                                            <td>{{ $enlevement->produit->nom }}</td>
                                            <td class="text-center"><b> {{ $enlevement->qte }}  </b></td>
                                            <td class="text-center"><b> {{ $enlevement->livraison->livreur->user->nom_prenoms }}  </b></td>
                                            <td class="text-center">
                                                {{ $enlevement->livraison->date_livraison }}
                                            {{-- <a href="" class="btn btn-success rounded font-sm">Accepter</a>
                                            <a href="" class="btn btn-danger rounded font-sm">Refuser</a> --}}
                                        </td>
                                        </tr>
                                        @endforeach

                                </tbody>
                            </table>
                        </div>
                        <!-- table-responsive //end -->
                    </div>


                    <!-- col.// -->
                </div>
                <!-- row.// -->
            </div>
            <!--  card-body.// -->
        </div>
        <!--  card.// -->
    @endsection
    @section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });
    </script>
@endsection
