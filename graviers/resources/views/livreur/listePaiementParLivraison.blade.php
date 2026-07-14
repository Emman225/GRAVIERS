@php
    use Illuminate\Support\carbon;
    $i = 1;
@endphp
@extends('layout.main')
@section('title','Liste des bons')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des bons en attente</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-9">
            <div class="card mb-4">
                @if (!$demandes->isEmpty())
                    <!-- card-header end// -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="text-center">N°</th>
                                        <th class="text-center">Montant</th>
                                        <th class="text-center">Date</th>
                                        <th class="text-center">Statut</th>
                                        <th class="text-center">Mode de paiement</th>
                                        {{-- <th class="text-center">Date de reception du bon</th>
                                        <th class="text-center">Date livraison prévu</th>
                                        <th class="text-end">Action</th> --}}
                                        {{-- <th class="text-end">Action</th> --}}
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- @php dd($enlevements) @endphp --}}
                                    @foreach ($demandes as $demande)
                                        <tr>

                                            <td class="text-center fw-bold"> {{ $demande->numero }} </td>
                                            <td class="text-center"><b> {{ number_format($demande->montant,'0','',' ') }}fcfa </b></td>
                                            <td class="text-center"><b> {{ Carbon::parse($demande->created_at)->format('d-m-Y') }} </b></td>
                                            <td class="text-center">
                                                @if ($demande->paye == 1)
                                                    <span class="badge bg-success">Payé</span>
                                                    @else
                                                    <span class="badge bg-danger">Non payé</span>

                                                @endif
                                            <td class="text-center">{{ $demande->paye == 1 ? $demande->modePaiement->libelle : '//' }}</td>

                                        </tr>
                                        @php
                                            $i++;
                                        @endphp
                                    @endforeach

                                </tbody>
                            </table>
                        </div>
                        <!-- table-responsive //end -->
                    </div>
                    <!-- card-body end// -->
                @else
                    <h1>Aucune demande de paiement pour l'instant</h1>
                @endif
            </div>
            <!-- card end// -->
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
            });
        });
    </script>
@endsection
