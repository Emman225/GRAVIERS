@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title', 'Grand livre des Clients BE')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Grand livre des Clients BE (Ordinaires) </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div> --}}
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-lg-4 col-md-6 me-auto">
                    @if (session('locked'))
                        <div class="alert alert-success" id="notify">
                            {{ session('locked') }}
                        </div>
                    @endif
                    @if (session('unlocked'))
                        <div class="alert alert-success" id="notify">
                            {{ session('unlocked') }}
                        </div>
                    @endif
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
                            <th class="text-center">Numéro de compte</th>
                            <th class="text-center">Date d'ouverture</th> {{--  --}}
                            <th class="text-center">Nom</th>
                            <th class="text-center">type de compte</th> {{--  --}}
                            <th class="text-center">Adresse géographique</th> {{--  --}}
                            <th class="text-center">Contact</th>
                            <th class="text-center">Email</th>
                            <th class="text-center width-10%" colspan="2">SOLDE</th>


                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @if (!is_null($user->client))
                                @if($user->client->client_a_terme == 0 && $user->client->deleted_at == null && $user->deleted_at == null )
                                    <tr>
                                        <td class="text-center">
                                            <div class="info pl-3">
                                                <h6 class="mb-0 title">{{ $user->id }}</h6>
                                            </div>
                                        </td>

                                        <td class="text-center">{{ Carbon::parse($user->created_at)->format('d-m-Y') }}</td>

                                        <td class="text-center"> <a href="{{route('grandLivre.clientOrdinaireDetail', $user->client)}}"> {{ $user->client->nom . ' ' . $user->client->prenom }}</a></td>

                                        <td class="text-center">|
                                            {{ $user->client->type_client }}
                                        </td>
                                        <td class="text-center">{{ $user->adresse }}</td>
                                        <td class="text-center">
                                            <p> {{ $user->client->contact1 }} </p>
                                            <p> {{ $user->client->contact2 }} </p>
                                        </td>
                                        <td class="text-center">{{ $user->email }}</td>
                                        <td>
                                            <h4> {{HELP::soldeClient($user->client)}} </h4>
                                        </td>

                                    </tr>
                                @endif
                            @endif

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
            var $table = $('.table').DataTable({
                columnDefs: [{ targets: '_all', defaultContent: '-' }],
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                }, order: [],
            });
        });
    </script>
@endsection
