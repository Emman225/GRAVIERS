@extends('layout.main')
@section('title','Balance âgée')

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Balance âgée</h2>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3 text-center">
                <div class="col-12 col-md-4">
                    <div class="text-muted small">Non échu</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totaux['non_echu']) }} fcfa</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-muted small">En retard</div>
                    <div class="h5 mb-0 text-danger">{{ $fmt($totalGeneral - $totaux['non_echu']) }} fcfa</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-muted small">Total général</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totalGeneral) }} fcfa</div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="balance-agee" title="Balance âgée" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Compte tiers</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Client</th>
                            {{-- Une facture pas encore due n'a pas de retard : elle tombait dans
                                 la tranche « 0 à 30 jours », qui mélangeait donc le non exigible
                                 et le retard récent. --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Non échu</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">A-[1 à 30 jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">B-[31 à 60 jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">C-[61 à 90 jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">D-[91 à 120 jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">E-[121 à 180 jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">F-[181 à 360 jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">G-[Plus de 360 jours]</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr>
                                <td class="text-center">{{ $l->client_id ?? '-' }}</td>
                                <td>{{ $l->client }}</td>
                                <td class="text-end">{{ $fmt($l->non_echu) }}</td>
                                <td class="text-end">{{ $fmt($l->t1_30) }}</td>
                                <td class="text-end">{{ $fmt($l->t31_60) }}</td>
                                <td class="text-end">{{ $fmt($l->t61_90) }}</td>
                                <td class="text-end">{{ $fmt($l->t91_120) }}</td>
                                <td class="text-end">{{ $fmt($l->t121_180) }}</td>
                                <td class="text-end">{{ $fmt($l->t181_360) }}</td>
                                <td class="text-end">{{ $fmt($l->t360_plus) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($l->total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
                                    Aucune créance ouverte.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if(count($lignes))
                        {{-- Le total par tranche : c'est la seule question qu'on pose
                             vraiment à une balance âgée, et elle n'y répondait pas. --}}
                        <tfoot>
                            <tr style="background-color:#1c57a3; color:white">
                                <td colspan="2" class="fw-bold text-end">Total par tranche</td>
                                <td class="text-end fw-bold">{{ $fmt($totaux['non_echu']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totaux['t1_30']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totaux['t31_60']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totaux['t61_90']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totaux['t91_120']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totaux['t121_180']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totaux['t181_360']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totaux['t360_plus']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totalGeneral) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Créances ouvertes uniquement, ventilées par ancienneté du retard. À défaut
                de date d'échéance saisie sur la facture, l'échéance est celle qu'induit le
                délai de paiement accordé au client.
            </p>
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
            // Une table vide — aucune créance ouverte — fait échouer DataTables sur
            // « Requested unknown parameter » : la ligne de repli n'a qu'une cellule.
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
