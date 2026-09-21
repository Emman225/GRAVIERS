
@php
    use Illuminate\Support\carbon;
@endphp
@extends('layout.main')
@section('title','Liste des produits')

@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title">Liste des produits</h2>
                        {{-- <p>Lorem ipsum dolor sit amet.</p> --}}
                    </div>
                    <div>
                        <a href="{{route('product.add')}}" class="btn btn-primary btn-sm rounded">Créer un nouveau produit</a>
                    </div>
                </div>
                <div class="card mb-4">
                    {{-- L en-tete ne contenait qu une case a cocher sans nom, sans
                         valeur et sans script : un « tout selectionner » herite du
                         gabarit, qui n a jamais rien selectionne. Un controle qui ne
                         fait rien use la confiance dans ceux qui font quelque chose. --}}
                    <div class="card-body">
                        <x-export-buttons table-id="liste"
                                          filename="catalogue-produits"
                                          title="Catalogue des produits" />
                        <div class="table-responsive">
                            <table id="liste" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nom et image</th>
                                        <th>Prix</th>
                                        <th>Catégoriee</th>
                                        <th>Statut</th>
                                        <th>Enregistré le</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($produits as $produit)

                                    {{-- @dd($infoProduct->statut$infoProduct->statut) --}}

                                        <tr>
                                            <td>
                                                <a class="itemside" href="#">
                                                    <div class="left">
                                                        @foreach ($produit->image as $image )
                                                            <img src="storage/{{$image->image}}" class="img-sm img-thumbnail" alt="Item" />
                                                        @endforeach
                                                    </div>
                                                    <div class="info">
                                                        <h6 class="mb-0">{{$produit->nom}} </h6>
                                                    </div>
                                                </a>
                                            </td>
                                            <td>
                                                <span>{{number_format($produit->prix_moyen,'0','',' ')}} fcfa</span>
                                            </td>
                                            <td>
                                                @php $i=1 @endphp
                                                    @foreach ($produit->categories as $categorie)
                                                    {{($i>1) ? '|' : ''}}   {{$categorie->nom}}
                                                    @php $i++ @endphp
                                                @endforeach

                                            </td>
                                            <td>
                                                @if ($produit->statut == \Help::$STATUT_ACTIF)
                                                    <span class="badge bg-success">Actif</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactif</span>
                                                @endif
                                            </td>
                                            <td>

                                                    <span>{{ Carbon::parse($produit->created_at)->format('d-m-Y'); }}</span>

                                            </td>
                                            <td class="text-nowrap text-end">

                                                    @if ($produit->statut == \Help::$STATUT_ACTIF)
                                                        <a href="{{route('product.toggle',$produit)}}" class="btn btn-sm font-sm btn-warning rounded" onclick="return confirm('Désactiver ce produit ? Il sera masqué du catalogue.')" title="Désactiver"><i class="material-icons md-visibility_off"></i></a>
                                                    @else
                                                        <a href="{{route('product.toggle',$produit)}}" class="btn btn-sm font-sm btn-success rounded" onclick="return confirm('Activer ce produit ? Il sera visible au catalogue.')" title="Activer"><i class="material-icons md-visibility"></i></a>
                                                    @endif
                                                    <a href="{{route('product.edit',$produit->id)}}" class="btn btn-sm font-sm rounded btn-primary" title="Modifier"><i class="material-icons md-edit"></i></a>
                                                    <a href="{{route('product.delete',$produit)}}" class="btn btn-sm font-sm btn-danger rounded" onclick="return confirm('Voulez vous supprimer ce produit??')" title="Supprimer"><i class="material-icons md-delete_forever"></i></a>

                                            </td>
                                        </tr>


                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

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
                    order: [],
                },
            });
        });
    </script>
@endsection
