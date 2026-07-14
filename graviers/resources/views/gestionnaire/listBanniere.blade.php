@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title','Liste des commandes')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des Bannières</h2>

        </div>
    </div>

        @if(session('success'))
            <div class="alert alert-success text-center" id="notify">
                {{session('success')}}
            </div>
        @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N° d'ordre</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Image</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Titre</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Sous_titre</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type de bannière</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date de publication</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Statut</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Modification</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Statut</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Supprimer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bannieres as $banniere)

                                    <tr>
                                        {{-- <td class="texte-center" ></a> </td>
                                        <td class="texte-center"></td> --}}
                                        <td class="text-center"> {{$banniere->num_ordre}} </td>
                                        <td class="text-center">
                                            <div class="left">
                                                <img src="storage/{{$banniere->image}}" class="img-sm img-thumbnail" alt="Item" />
                                            </div>
                                        </td>
                                        <td class="h5"> <a href="">{{$banniere->titre}}</a></td>
                                        <td class="h5"> <a href="">{{$banniere->sous_titre}}</a></td>
                                        <td class="h5"> <a href="">{{$banniere->type_banniere}}</a></td>
                                        <td class="text-center"> {{$banniere->date_heure_decompte}}</td>
                                        <td class="text-center">
                                            @if ($banniere->statut == 1)
                                                <span class="badge bg-success"> En ligne </span>
                                            @else
                                                <span class="badge bg-secondary"> Pas en ligne </span>
                                            @endif
                                        </td>

                                        <td class="text-end">
                                            <a href="{{route('show.modificationDeBannierePage',$banniere->id)}}" class="btn btn-primary">Modifier</a>
                                        </td>

                                        <td>

                                            @if ($banniere->statut == 1)
                                                <a href="{{route('show.supprimerPublierBanniere',['id' => $banniere->id, 'action' => 'enLigne'])}}" class="btn btn-danger">Retirer</a>
                                            @else
                                                <a href="{{route('show.supprimerPublierBanniere',['id' => $banniere->id, 'action' => 'enLigne'])}}" class="btn btn-success">Republier</a>
                                            @endif
                                        </td>

                                        <td>
                                            <a href="{{route('show.supprimerPublierBanniere',['id' => $banniere->id, 'action' => 'supprimer'])}}" class="btn btn-danger">Supprimer</a>
                                        </td>
                                    </tr>

                                @endforeach

                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive //end -->
                </div>
                <!-- card-body end// -->
            </div>
            <!-- card end// -->
        </div>
        <div class="col-md-3">

        </div>
    </div>
    <div class="pagination-area mt-15 mb-50">

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
                },
            });
        });
    </script>
    @notifyJs
@endsection
