@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Liste des fournisseurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des fournisseurs </h2>
        <div>
            <a href="{{ route('show.registerSeller') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div>
    </div>
    <div class="card mb-4">
        {{-- <header class="card-header">
            <div class="row gx-3">
                <div class="col-lg-4 col-md-6 me-auto">
                    <input type="text" placeholder="Recherche..." class="form-control" />
                </div>
            </div>
        </header> --}}
        <!-- card-header end// -->
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead>
                        <tr>
                            <th class="text-center">Numéro de compte</th>
                            {{-- @role('Admin') --}}
                            <th class="text-center">Date d'ouverture</th> {{--  --}}
                            {{-- @endrole --}}
                            <th class="text-center">Nom</th>
                           {{-- 0 @role('Admin') --}}

                            <th class="text-center">Adresse géographique</th> {{--  --}}
                            {{-- @endrole --}}
                            <th class="text-center">Contact</th>

                            {{-- @role('Admin') --}}

                            <th class="text-center">&nbsp;</th> {{--  --}}
                            {{-- @endrole --}}
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fournisseurs as $fournisseur )
                            <tr>
                                <td class="text-center" >
                                    <div class="info pl-3">
                                        <h6 class="mb-0 title">{{$fournisseur->user_id}}</h6>
                                    </div>
                                </td>
                                {{-- @role('Admin') --}}
                                <td class="text-center">{{ Carbon::parse($fournisseur->created_at)->format('d-m-Y'); }}</td>
                                {{-- @endrole --}}
                                <td class="text-center">{{$fournisseur->nom_prenoms}}</td>
                                {{-- @role('Admin') --}}

                                <td class="text-center">{{$fournisseur->adresse_geo}}</td>
                                {{-- @endrole --}}
                                <td class="text-center">
                                    <p> {{$fournisseur->contact1}} </p>
                                    <p> {{$fournisseur->contact2}} </p>
                                </td>
                                <td class="text-end">
                                    <a href="{{route('show.bonParFournisseur',$fournisseur)}}" class="btn btn-sm btn-brand rounded font-sm mt-15">Détails des enlèvements</a>
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
