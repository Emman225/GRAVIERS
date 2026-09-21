@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'État de TVA collectée')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">État de TVA collectée</h2>
    </div>

    {{-- ============ Période ============ --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('show.comptabilite.tvaCollectee') }}" class="row gx-3 align-items-end">
                <div class="col-lg-3 col-md-4 mb-2">
                    <label class="form-label">Du</label>
                    <input type="date" class="form-control" name="du" value="{{ $du->format('Y-m-d') }}">
                </div>
                <div class="col-lg-3 col-md-4 mb-2">
                    <label class="form-label">Au</label>
                    <input type="date" class="form-control" name="au" value="{{ $au->format('Y-m-d') }}">
                </div>
                <div class="col-lg-3 col-md-4 mb-2">
                    <label class="form-label">Activité</label>
                    <select class="form-control" name="service">
                        <option value="TOUS" {{ $service === 'TOUS' ? 'selected' : '' }}>Toutes</option>
                        <option value="VENTE" {{ $service === 'VENTE' ? 'selected' : '' }}>Ventes</option>
                        <option value="LOCATION" {{ $service === 'LOCATION' ? 'selected' : '' }}>Locations</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-12 mb-2">
                    <button type="submit" class="btn btn-primary w-100">Afficher</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ============ Les deux chiffres de la déclaration ============ --}}
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <span class="text-muted small">Base hors taxe</span><br>
                    <strong class="h4">{{ Help::formatNombre($totaux->base_ht, true) }}</strong>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-primary">
                <div class="card-body text-center">
                    <span class="text-muted small">TVA facturée</span><br>
                    <strong class="h4 text-primary">{{ Help::formatNombre($totaux->tva_facturee, true) }}</strong><br>
                    <small class="text-muted">inscrite sur les factures</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-success">
                <div class="card-body text-center">
                    <span class="text-muted small">TVA encaissée</span><br>
                    <strong class="h4 text-success">{{ Help::formatNombre($totaux->tva_encaissee, true) }}</strong><br>
                    <small class="text-muted">réellement reçue du client</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-body text-center">
                    <span class="text-muted small">Reste à encaisser</span><br>
                    <strong class="h4 text-warning">{{ Help::formatNombre($totaux->tva_a_encaisser, true) }}</strong><br>
                    <small class="text-muted">encore chez le client</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Ce que l'état dit, et ce qu'il ne dit pas ============ --}}
    <div class="alert alert-info">
        <strong><i class="material-icons md-info" style="vertical-align: middle;"></i> Comment lire cet état</strong>
        <ul class="mb-0 mt-2">
            <li>
                La <strong>TVA facturée</strong> est celle portée par les factures de la période.
                La <strong>TVA encaissée</strong> en est la part que le client a réellement réglée :
                sur une facture payée à moitié, la moitié de la taxe est encore chez lui.
                Elle se calcule au prorata du règlement — le client verse un acompte sur un tout,
                pas « d'abord le produit puis la taxe ».
            </li>
            <li>
                Le <strong>transport n'est pas taxé</strong> : il n'apparaît donc pas dans cet état,
                ni dans les bases hors taxe ci-dessus.
            </li>
            <li>
                Cette TVA est <strong>purement collectée</strong> tant qu'aucun fournisseur n'est coché
                « assujetti à la TVA » sur sa fiche : il n'y a alors rien à déduire, et le montant dû
                à l'État est la TVA collectée elle-même.
            </li>
        </ul>
    </div>

    @if ($nbAnomalies > 0)
        <div class="alert alert-warning">
            <strong>{{ $nbAnomalies }} facture(s) à vérifier.</strong>
            Leur taux effectif s'écarte du taux paramétré ({{ rtrim(rtrim(number_format($tauxConfig, 2, ',', ' '), '0'), ',') }} %) :
            la base a changé après le calcul de la taxe. Les lignes concernées sont surlignées.
            Le montant d'origine est conservé tel quel — c'est lui qui fait foi comptablement.
        </div>
    @endif

    {{-- ============ Ventilation par activité ============ --}}
    @if ($parService->count() > 1)
        <div class="card mb-4">
            <header class="card-header"><h6 class="mb-0">Ventilation par activité</h6></header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead style="background-color: #1c57a3; color: white;">
                            <tr>
                                <th>Activité</th>
                                <th class="text-center">Nb factures</th>
                                <th class="text-end">Base HT</th>
                                <th class="text-end">TVA facturée</th>
                                <th class="text-end">TVA encaissée</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($parService as $s)
                                <tr>
                                    <td>{{ $s->service }}</td>
                                    <td class="text-center">{{ $s->nb }}</td>
                                    <td class="text-end">{{ Help::formatNombre($s->base_ht, true) }}</td>
                                    <td class="text-end">{{ Help::formatNombre($s->tva_facturee, true) }}</td>
                                    <td class="text-end text-success">{{ Help::formatNombre($s->tva_encaissee, true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Le détail, facture par facture ============ --}}
    <div class="card mb-4">
        <header class="card-header">
            <h6 class="mb-0">
                Détail du {{ $du->format('d/m/Y') }} au {{ $au->format('d/m/Y') }}
                — {{ $lignes->count() }} facture(s)
            </h6>
        </header>
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="etat-tva-collectee"
                title="État de TVA collectée" />
            <div class="table-responsive">
                <table class="table table-striped table-sm" id="liste" style="font-size: 0.85rem;">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date</th>
                            <th class="text-center">N° Facture</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">Activité</th>
                            <th class="text-end">Base HT</th>
                            <th class="text-end">Taux</th>
                            <th class="text-end">TVA facturée</th>
                            <th class="text-end">Net à payer</th>
                            <th class="text-end">Encaissé</th>
                            <th class="text-end">TVA encaissée</th>
                            <th class="text-center">Règlement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr class="{{ $l->anomalie ? 'table-warning' : '' }}">
                                <td class="text-center">{{ \Help::dateHeure($l->date) ?? '-' }}</td>
                                <td class="text-center"><strong>{{ $l->numero }}</strong></td>
                                <td>{{ $l->client }}</td>
                                <td class="text-center">{{ $l->service }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->base_ht, true) }}</td>
                                <td class="text-end">
                                    {{ rtrim(rtrim(number_format($l->taux, 2, ',', ' '), '0'), ',') }} %
                                    @if ($l->anomalie)
                                        <i class="material-icons md-warning text-warning" style="font-size: 15px; vertical-align: middle;"
                                           title="Taux différent du taux paramétré : la base a changé après le calcul de la taxe."></i>
                                    @endif
                                </td>
                                <td class="text-end"><strong>{{ Help::formatNombre($l->tva_facturee, true) }}</strong></td>
                                <td class="text-end">{{ Help::formatNombre($l->net_a_payer, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->encaisse, true) }}</td>
                                <td class="text-end text-success"><strong>{{ Help::formatNombre($l->tva_encaissee, true) }}</strong></td>
                                <td class="text-center">
                                    @if ($l->solde === 'Soldée')
                                        <span class="badge bg-success">Soldée</span>
                                    @elseif ($l->solde === 'Partielle')
                                        <span class="badge bg-warning text-dark">Partielle</span>
                                    @else
                                        <span class="badge bg-danger">Impayée</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted">
                                    Aucune facture avec TVA sur cette période.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="4" class="text-end">TOTAUX</td>
                                <td class="text-end">{{ Help::formatNombre($totaux->base_ht, true) }}</td>
                                <td></td>
                                <td class="text-end text-primary">{{ Help::formatNombre($totaux->tva_facturee, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totaux->net_a_payer, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totaux->encaisse, true) }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($totaux->tva_encaissee, true) }}</td>
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
