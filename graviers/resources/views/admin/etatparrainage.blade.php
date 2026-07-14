@extends('layout.main')
@section('title','Etat de paiement filleule')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">- Etat de paiement filleule - </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div> --}}
    </div>
    <div class="card mb-4">
        {{-- <form method="GET" action="" class="row">
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
        </form> --}}
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-lg-4 col-md-6 me-auto">
                    <input type="text" placeholder="Recherche..." class="form-control" />
                </div>
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead>
                        <tr>
                            <th class="text-center">Client</th>
                            <th class="text-center">Code Apporteur d'affaire</th>
                            <th class="text-center">Apporteur d'affaire</th>
                            <th class="text-center">Total Paiement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paiements as $p )
                            <tr>
                                <td class="text-center" >
                                    <div class="info pl-3">
                                        <h6 class="mb-0 title">{{$p->client}}</h6>
                                    </div>
                                </td>

                                <td class="text-center">{{ $p->codeApporteur}}</td>

                                <td class="text-center">{{$p->apporteur}}</td>

                                <td class="text-center">

                                    {{HELP::formatNombre($p->total, true)}}

                                </td>
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
