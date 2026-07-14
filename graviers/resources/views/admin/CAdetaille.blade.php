@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Chiffre d\'affaire détaillé')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">- Chiffre d'affaire détaillé - </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div> --}}
    </div>
    <div class="card mb-4">
        <form method="GET" action="" class="row">
            @csrf
            <div class="col-md-2 col-lg-2 col-xl-2">
                <input type="date" class="form-control" name="du" id="du" placeholder="Date debut" value="{{ Request::get('du') ?? date("Y-01-01") }}">
            </div>
            <div class="col-md-2 col-lg-2 col-xl-2">
                <input type="date" class="form-control" name="au" id="au" placeholder="Date debut" value="{{ Request::get('au') ?? date("Y-m-d") }}">
            </div>
            <div class="col-md-2 col-lg-2 col-xl-2">
                <button type="submit" class="btn btn-primary ">Rechercher</button>
            </div>
        </form>
        {{-- <header class="card-header">
            <div class="row gx-3">
                <div style="width:100%" class="col-lg-4 col-md-6 me-auto">
                    <p class="d-flex justify-content-between" >
                        <span class="text-success h4" >T. Qté vendue : 0</span>
                        <span class="text-success h4" >T. TVA : 50</span>
                        <span class="text-success h4" >T. Montant : 0 fcfa</span>
                    </p>
                </div>
            </div>
        </header> --}}
        <!-- card-header end// -->
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="chiffre-d-affaire-detaille" title="Etat chiffre d'affaire détaillé" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background: gray">

                        <th class="text-center" style="background-color: #1c57a3; color: white;">Désignation</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Famille</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité vendue</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité dispo</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Montant vendu</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Montant fournisseur</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Bénéfice</th>
                    </thead>
                    <tbody>

                        @foreach ($stats as $s)
                        
                            <tr>
                                <td class="text-center" ><div class="info pl-3"><h6 class="mb-0 title">{{ $s->nom }}</h6></div></td>
                                <td class="text-center">{{ $s->categories }}</td>
                                <td class="text-center">{{ $s->qteVendu }}</td>
                                <td class="text-center">{{ $s->qteDispo }}</td>
                                <td class="text-center">{{ $s->prixVente }}</td>
                                <td class="text-center">{{ $s->prixFournisseur }}</td>
                                <td class="text-center">{{ $s->prixVente - $s->prixFournisseur }}</td>
                            </tr>
                        @endforeach

                    </tbody>
                </table>
                <!-- table-responsive.// -->
            </div>
        </div>
        <!-- card-body end// -->
    </div>
    <!-- card end// -->

@endsection


@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });
    </script>
@endsection
