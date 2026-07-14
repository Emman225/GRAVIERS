@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Balande agée')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Balande agée - </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div> --}}
    </div>
    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div style="width:100%" class="col-lg-4 col-md-6 me-auto">
                    <p class="d-flex justify-content-between" >
                        <span class="text-success h4">Total Général : {{ number_format($totalGeneral, 0, '', ' ') }} fcfa</span>
                    </p>
                </div>
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="balance-agee" title="Balance âgée" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Clients/Durée des créances</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">A-[0 à 30 Jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">B-[31 à 60 Jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">C-[61 à 90 Jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">D-[91 à 120 Jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">E-[121 à 180 Jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">F-[181 à 360 Jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">G-[Plus de 360 Jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Total général</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lignes as $l)
                            <tr>
                                <td class="text-center"><div class="info pl-3"><h6 class="mb-0 title">{{ $l->client }}</h6></div></td>
                                <td class="text-center">{{ number_format($l->t0_30, 0, '', ' ') }}</td>
                                <td class="text-center">{{ number_format($l->t31_60, 0, '', ' ') }}</td>
                                <td class="text-center">{{ number_format($l->t61_90, 0, '', ' ') }}</td>
                                <td class="text-center">{{ number_format($l->t91_120, 0, '', ' ') }}</td>
                                <td class="text-center">{{ number_format($l->t121_180, 0, '', ' ') }}</td>
                                <td class="text-center">{{ number_format($l->t181_360, 0, '', ' ') }}</td>
                                <td class="text-center">{{ number_format($l->t360_plus, 0, '', ' ') }}</td>
                                <td class="text-center"><b>{{ number_format($l->total, 0, '', ' ') }}</b></td>
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
