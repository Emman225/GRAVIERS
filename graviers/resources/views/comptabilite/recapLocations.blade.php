@extends('layout.main')
@section('title','Récapitulatif des locations')

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Récapitulatif des locations</h2>
    </div>

    @include('comptabilite._beneficeDalakoun', [
        'titre'          => 'Bénéfices de DALAKOUN sur les locations',
        'produit'        => $totaux->loue,
        'charge'         => $totaux->cout,
        'libelleProduit' => 'Loué aux clients (HT)',
        'libelleCharge'  => 'Coût du matériel',
        'benefice'       => $totaux->marge,
        'note'           => "Sur la période affichée, remises déduites. Rien n'enregistre le tarif du fournisseur au moment de la location : le coût est reconstitué à partir des prix d'achat en vigueur aujourd'hui, multipliés par la quantité et la durée. C'est donc une estimation. La caution n'entre pas dans le calcul : elle appartient au client.",
    ])

    <div class="card mb-4">
        {{-- Formulaire en GET : pas de @csrf, le jeton se retrouverait dans l'URL. --}}
        <form method="GET" action="" class="row gx-3 p-3 align-items-center">
            <div class="col-md-3 col-lg-2">
                <label for="du" class="form-label mb-1 small text-muted">Du</label>
                <input type="date" class="form-control" name="du" id="du" value="{{ $du }}">
            </div>
            <div class="col-md-3 col-lg-2">
                <label for="au" class="form-label mb-1 small text-muted">Au</label>
                <input type="date" class="form-control" name="au" id="au" value="{{ $au }}">
            </div>
            <div class="col-md-3 col-lg-3 pt-4">
                <button type="submit" class="btn btn-primary">Rechercher</button>
            </div>
            <div class="col-md-3 col-lg-5 pt-4 text-md-end">
                <span class="text-muted small">
                    Locations du {{ \Carbon\Carbon::parse($du)->format('d/m/Y') }}
                    au {{ \Carbon\Carbon::parse($au)->format('d/m/Y') }}
                </span>
            </div>
        </form>

        <header class="card-header">
            <div class="row gx-3 text-center">
                <div class="col-6 col-md">
                    <div class="text-muted small">Locations</div>
                    <div class="h5 mb-0">{{ count($lignes) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Jours-matériel</div>
                    <div class="h5 mb-0">{{ $fmt($totaux->jours) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Montant dû (TTC)</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totaux->montant) }} fcfa</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Reste à encaisser</div>
                    <div class="h5 mb-0 {{ $totaux->reste > 0 ? 'text-warning' : 'text-muted' }}">
                        {{ $fmt($totaux->reste) }} fcfa
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Cautions détenues</div>
                    <div class="h5 mb-0 text-info">{{ $fmt($totaux->caution) }} fcfa</div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste"
                              filename="recapitulatif-locations"
                              title="Récapitulatif des locations" />
            <div class="table-responsive">
                <table class="table table-striped align-middle" id="liste">
                    <thead>
                        <tr>
                            <th style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Location</th>
                            <th style="background-color: #1c57a3; color: white;">Date</th>
                            <th style="background-color: #1c57a3; color: white;">Client</th>
                            <th style="background-color: #1c57a3; color: white;">Matériel</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Jours-matériel</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Loué HT</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Coût matériel</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Bénéfice</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Montant dû</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Encaissé</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Reste dû</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Caution</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">État</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td>{{ $ligne->numero ?: '—' }}</td>
                                <td>{{ $ligne->date ? \Help::dateHeure($ligne->date) : '—' }}</td>
                                <td>{{ $ligne->client }}</td>
                                <td>{{ $ligne->materiels }}</td>
                                <td class="text-end">{{ $fmt($ligne->jours) }}</td>
                                <td class="text-end">{{ $fmt($ligne->loue) }}</td>
                                <td class="text-end text-danger">{{ $fmt($ligne->cout) }}</td>
                                <td class="text-end {{ $ligne->marge >= 0 ? 'text-success' : 'text-danger fw-bold' }}">
                                    {{ $fmt($ligne->marge) }}
                                </td>
                                <td class="text-end">{{ $fmt($ligne->montant) }}</td>
                                <td class="text-end">{{ $fmt($ligne->encaisse) }}</td>
                                <td class="text-end {{ $ligne->reste > 0 ? 'text-warning fw-bold' : 'text-muted' }}">
                                    {{ $fmt($ligne->reste) }}
                                </td>
                                <td class="text-end">
                                    {{ $fmt($ligne->caution) }}
                                    @if ($ligne->caution > 0)
                                        <br><small class="{{ $ligne->rendue ? 'text-muted' : 'text-info' }}">
                                            {{ $ligne->rendue ? 'restituée' : 'détenue' }}
                                        </small>
                                    @endif
                                </td>
                                <td class="text-center">{{ $ligne->etat }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted py-5">
                                    Aucune location sur cette période.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (count($lignes))
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4">Total</td>
                                <td class="text-end">{{ $fmt($totaux->jours) }}</td>
                                <td class="text-end">{{ $fmt($totaux->loue) }}</td>
                                <td class="text-end text-danger">{{ $fmt($totaux->cout) }}</td>
                                <td class="text-end {{ $totaux->marge >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $fmt($totaux->marge) }}
                                </td>
                                <td class="text-end">{{ $fmt($totaux->montant) }}</td>
                                <td class="text-end">{{ $fmt($totaux->encaisse) }}</td>
                                <td class="text-end">{{ $fmt($totaux->reste) }}</td>
                                <td class="text-end">{{ $fmt($totaux->caution) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Un matériel loué cinq jours en deux exemplaires compte dix jours-matériel :
                c'est la base de facturation. La <strong>caution n'est pas un produit</strong> :
                elle appartient au client tant qu'elle n'est pas retenue, et le total ci-dessus
                ne compte que les cautions encore détenues. Elle n'entre ni dans le montant loué
                ni dans la marge.
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
            var $corps = $('#liste tbody');
            if ($corps.find('td[colspan]').length) {
                return;
            }

            $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                columnDefs: [{ targets: '_all', defaultContent: '-' }],
            });
        });
    </script>
@endsection
