
@php
    use Illuminate\Support\carbon;
@endphp
@extends('layout.main')
@section('title','Liste des gestionnaires')

@section('contenu')
<meta name="csrf-token" content="{{ csrf_token() }}">
                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title">Liste des agents</h2>
                    </div>
                    <div>

                        <a href="{{route('show.AgentRegister')}}" class="btn btn-primary btn-sm rounded">Ajouter un Agent</a>
                    </div>
                </div>
                <div class="card mb-4">
                    <header class="card-header">

                    </header>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="liste" class="table table-striped">
                                <thead >
                                    <tr >
                                        <th style="background-color: rgb(195, 195, 195)" >Nom et image</th>
                                        <th style="background-color: rgb(195, 195, 195)">Email</th>

                                        <th style="background-color: rgb(195, 195, 195)">Enregistré le</th>
                                        <th style="background-color:  rgb(195, 195, 195)" class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($agents as $agent)
                                    <tr>
                                        <td>
                                            <a class="itemside" href="#">
                                                <div class="left">
                                                    <img src="storage/{{$agent->photo}}" class="img-sm img-thumbnail" alt="Item" />
                                                </div>
                                                <div class="info">
                                                    <h6 class="mb-0">{{$agent->nom_prenoms}}</h6>
                                                </div>
                                            </a>
                                        </td>
                                        <td> {{$agent->email}} </td>

                                        <td>

                                            <span>{{ Carbon::parse($agent->created_at)->format('d-m-Y'); }}</span>

                                        </td>
                                        <td class="text-end">

                                                <a href="{{route('show.AgentUpdate',$agent)}}" class="btn btn-sm font-sm rounded btn-brand"> <i class="material-icons md-edit"></i> Modifier </a>
                                                <a href="javascript:void(0)" class="btn btn-sm font-sm btn-light rounded" onclick="deleteAgentAction({{ $agent->id }}, '{{ addslashes($agent->user?->nom_prenoms ?? 'cet agent') }}'); return false;"> <i class="material-icons md-delete_forever"></i> Supprimer </a>
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
                },
                order: [],
            });
        });
    </script>


    <script>
        function deleteAgentAction(userId, agentName) {
            window.confirmDelete({
                itemName: agentName,
                text: "L'agent ne pourra plus accéder à la plateforme. Cette action est irréversible.",
                onConfirm: function () {
                    fetch('/liste-agent/' + userId, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Erreur lors de la suppression.');
                        return response.json();
                    })
                    .then(function (data) {
                        window.showToast(data.message || 'Agent supprimé avec succès.', 'success');
                        setTimeout(function () { location.reload(); }, 800);
                    })
                    .catch(function (error) {
                        window.showToast('Erreur : ' + error.message, 'error');
                    });
                }
            });
        }
    </script>

@endsection
