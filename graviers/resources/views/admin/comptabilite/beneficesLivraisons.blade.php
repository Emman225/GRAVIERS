@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Bénéfices sur les livraisons')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Bénéfices sur les livraisons</h2>
    </div>

    @include('comptabilite._beneficeDalakoun', [
        'titre'          => 'Bénéfices de DALAKOUN sur les livraisons',
        'produit'        => $totaux->facture,
        'charge'         => $totaux->verse,
        'libelleProduit' => 'Facturé aux clients',
        'libelleCharge'  => 'Versé aux livreurs',
        'benefice'       => $totaux->marge,
        'note'           => "Sur la période affichée. Le transport d'un document se répartit entre ses livraisons. Les retraits sur place sont exclus : aucun livreur n'y intervient.",
    ])

    {{-- ============ Période ============ --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('show.comptabilite.beneficesLivraisons') }}"
                  class="row gx-3 align-items-end">
                <div class="col-lg-4 col-md-5 mb-2">
                    <label class="form-label">Du</label>
                    <input type="date" class="form-control" name="du" value="{{ $du->format('Y-m-d') }}">
                </div>
                <div class="col-lg-4 col-md-5 mb-2">
                    <label class="form-label">Au</label>
                    <input type="date" class="form-control" name="au" value="{{ $au->format('Y-m-d') }}">
                </div>
                <div class="col-lg-4 col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary w-100">Afficher</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ============ Facturé, versé, marge ============ --}}
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <span class="text-muted small">Livraisons</span><br>
                    <strong class="h4">{{ $totaux->nb }}</strong>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-primary">
                <div class="card-body text-center">
                    <span class="text-muted small">Transport facturé</span><br>
                    <strong class="h4 text-primary">{{ Help::formatNombre($totaux->facture, true) }}</strong><br>
                    <small class="text-muted">encaissé du client</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-danger">
                <div class="card-body text-center">
                    <span class="text-muted small">Versé aux livreurs</span><br>
                    <strong class="h4 text-danger">{{ Help::formatNombre($totaux->verse, true) }}</strong><br>
                    <small class="text-muted">coût de la course</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 {{ $totaux->marge < 0 ? 'border-danger' : 'border-success' }}">
                <div class="card-body text-center">
                    <span class="text-muted small">Marge</span><br>
                    <strong class="h4 {{ $totaux->marge < 0 ? 'text-danger' : 'text-success' }}">
                        {{ Help::formatNombre($totaux->marge, true) }}
                    </strong><br>
                    <small class="text-muted">
                        @if (!is_null($totaux->taux))
                            {{ number_format($totaux->taux, 1, ',', ' ') }} % du facturé
                        @else
                            aucun transport facturé
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Comment lire ============ --}}
    <div class="alert alert-info">
        <strong><i class="material-icons md-info" style="vertical-align: middle;"></i> Comment lire cet état</strong>
        <ul class="mb-0 mt-2">
            <li>
                <strong>Facturé</strong> vient de la grille tarifaire (ce que paie le client),
                <strong>versé</strong> de la fiche du livreur (forfait, kilométrage ou les deux,
                multipliés par le nombre de voyages). Ce sont deux réglages indépendants :
                cet écran est le seul endroit qui les confronte.
            </li>
            <li>
                Quand une commande est livrée en plusieurs fois, son transport est
                <strong>réparti entre les livraisons au prorata des quantités</strong>.
                Sans cela, la première course porterait tout le chiffre d'affaires
                et les suivantes apparaîtraient à perte.
            </li>
            <li>
                Le <strong>retrait sur place est exclu</strong> : sans transport facturé ni livreur payé,
                ces lignes ne diraient rien de la rentabilité.
                @if ($retraitsExclus > 0)
                    <strong>{{ $retraitsExclus }}</strong> livraison(s) écartée(s) à ce titre.
                @endif
            </li>
        </ul>
    </div>

    @if ($nbPertes > 0)
        <div class="alert alert-danger">
            <strong>{{ $nbPertes }} livraison(s) à perte.</strong>
            Le livreur y coûte plus cher que le transport facturé au client.
            Les lignes concernées sont surlignées : à corriger dans la grille tarifaire
            (menu <em>Client ordinaire › Grille tarifaire livraisons</em>) ou sur la fiche du livreur.
        </div>
    @endif

    {{-- ============ Par livreur ============ --}}
    @if ($parLivreur->count() > 0)
        <div class="card mb-4">
            <header class="card-header">
                <h6 class="mb-0">Par livreur — les moins rentables en premier</h6>
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead style="background-color: #1c57a3; color: white;">
                            <tr>
                                <th>Livreur</th>
                                <th class="text-center">Courses</th>
                                <th class="text-end">Distance</th>
                                <th class="text-end">Facturé</th>
                                <th class="text-end">Versé</th>
                                <th class="text-end">Marge</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($parLivreur as $lv)
                                <tr class="{{ $lv->marge < 0 ? 'table-danger' : '' }}">
                                    <td>{{ $lv->livreur }}</td>
                                    <td class="text-center">{{ $lv->nb }}</td>
                                    <td class="text-end">
                                        {{ $lv->distance > 0 ? rtrim(rtrim(number_format($lv->distance, 2, ',', ' '), '0'), ',') . ' km' : '-' }}
                                    </td>
                                    <td class="text-end">{{ Help::formatNombre($lv->facture, true) }}</td>
                                    <td class="text-end">{{ Help::formatNombre($lv->verse, true) }}</td>
                                    <td class="text-end {{ $lv->marge < 0 ? 'text-danger' : 'text-success' }}">
                                        <strong>{{ Help::formatNombre($lv->marge, true) }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Le détail, livraison par livraison ============ --}}
    <div class="card mb-4">
        <header class="card-header">
            <h6 class="mb-0">
                Détail du {{ $du->format('d/m/Y') }} au {{ $au->format('d/m/Y') }}
                — {{ $lignes->count() }} livraison(s)
            </h6>
        </header>
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="benefices-livraisons"
                title="Bénéfices sur les livraisons" />
            <div class="table-responsive">
                <table class="table table-striped table-sm" id="liste" style="font-size: 0.85rem;">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date</th>
                            <th class="text-center">N° Livraison</th>
                            <th class="text-center">Activité</th>
                            <th class="text-center">Document</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">Livreur</th>
                            <th class="text-end">Distance</th>
                            <th class="text-end">Quantité</th>
                            <th class="text-end">Facturé</th>
                            <th class="text-end">Versé</th>
                            <th class="text-end">Marge</th>
                            <th class="text-end">Taux</th>
                            <th class="text-center">État</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr class="{{ $l->perte ? 'table-danger' : '' }}">
                                <td class="text-center">{{ \Help::dateHeure($l->date) }}</td>
                                <td class="text-center"><strong>{{ $l->numero }}</strong></td>
                                <td class="text-center">{{ $l->service }}</td>
                                <td class="text-center">{{ $l->document }}</td>
                                <td>{{ $l->client }}</td>
                                <td>{{ $l->livreur }}</td>
                                <td class="text-end">
                                    {{ $l->distance > 0 ? rtrim(rtrim(number_format($l->distance, 2, ',', ' '), '0'), ',') . ' km' : '-' }}
                                </td>
                                <td class="text-end">
                                    {{ $l->quantite > 0 ? rtrim(rtrim(number_format($l->quantite, 2, ',', ' '), '0'), ',') : '-' }}
                                </td>
                                <td class="text-end">{{ Help::formatNombre($l->facture, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->verse, true) }}</td>
                                <td class="text-end {{ $l->perte ? 'text-danger' : 'text-success' }}">
                                    <strong>{{ Help::formatNombre($l->marge, true) }}</strong>
                                </td>
                                <td class="text-end">
                                    @if (is_null($l->taux))
                                        <span class="text-muted">-</span>
                                    @else
                                        {{ number_format($l->taux, 1, ',', ' ') }} %
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $l->etat === 'LIVREE' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $l->etat }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted">
                                    Aucune livraison facturable sur cette période.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="8" class="text-end">TOTAUX</td>
                                <td class="text-end text-primary">{{ Help::formatNombre($totaux->facture, true) }}</td>
                                <td class="text-end text-danger">{{ Help::formatNombre($totaux->verse, true) }}</td>
                                <td class="text-end {{ $totaux->marge < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ Help::formatNombre($totaux->marge, true) }}
                                </td>
                                <td class="text-end">
                                    {{ is_null($totaux->taux) ? '-' : number_format($totaux->taux, 1, ',', ' ') . ' %' }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
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
            // Garde-fou : DataTables lève « Requested unknown parameter » sur une
            // table sans ligne de données (cas d'une période vide).
            if ($('#liste tbody tr td').length > 1) {
                $('#liste').DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [],
                    pageLength: 25,
                });
            }
        });
    </script>
@endsection
