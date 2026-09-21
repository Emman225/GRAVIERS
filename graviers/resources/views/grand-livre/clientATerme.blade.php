@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title', 'Liste des clients')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des Clients à terme - </h2>

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
            <x-export-buttons table-id="liste"
                              filename="grand-livre-clients-a-terme"
                              title="Grand livre — clients à terme" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead>
                        <tr>
                            <th class="text-center">Numéro de compte</th>
                            <th class="text-center">Date d'ouverture</th>
                            <th class="text-center">Nom</th>
                            <th class="text-center">Type de compte</th>
                            <th class="text-center">Adresse géographique</th>
                            <th class="text-center">Contact</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Solde</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clients as $client)
                            <tr>
                                <td class="text-center">{{ $client->user?->id }}</td>
                                <td class="text-center">{{ $client->user?->created_at ? \Carbon\Carbon::parse($client->user->created_at)->format('d-m-Y') : '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('grandLivre.clientOrdinaireDetail', $client) }}">{{ $client->display_name }}</a>
                                </td>
                                <td class="text-center">{{ $client->type_client }}</td>
                                <td class="text-center">{{ $client->user?->adresse ?: '-' }}</td>
                                <td class="text-center">
                                    {{ $client->contact1 ?: '-' }}
                                    @if($client->contact2)
                                        <br>{{ $client->contact2 }}
                                    @endif
                                </td>
                                <td class="text-center">{{ $client->user?->email ?: '-' }}</td>
                                <td class="text-center fw-bold">{{ Help::soldeClient($client, 1) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    Aucun client dans ce grand livre.
                                </td>
                            </tr>
                        @endforelse
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
            // Le selecteur portait sur TOUTES les tables de la page, et rien
            // n'empechait l'initialisation sur une table vide — « Requested
            // unknown parameter » des qu'aucun client ne repond au filtre.
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: {
                        url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                    },
                    order: [],
                });
            }
        });
    </script>
@endsection
