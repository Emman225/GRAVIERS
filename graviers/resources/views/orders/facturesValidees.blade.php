@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title', 'Factures validées (FNE)')

@section('contenu')
    <section class="content-main">
        <div class="content-header">
            <h2 class="content-title">Factures validées – Certifiées par la DGI (FNE)</h2>
        </div>

        <div class="card">
            <div class="card-body">

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if($factures->isEmpty())
                    <h4 class="text-center my-4 text-muted">
                        Aucune facture certifiée par la DGI pour le moment.
                    </h4>
                @else
                    <x-export-buttons table-id="tableFacturesValidees" filename="factures-validees" title="Factures validées (FNE)" />
                    <div class="table-responsive">
                        <table class="table table-striped" id="tableFacturesValidees">
                            <thead style="background-color: #1c57a3; color: white;">
                                <tr>
                                    <th>N° Facture</th>
                                    <th>Réf. FNE (DGI)</th>
                                    <th>Commande / Location</th>
                                    <th>Client</th>
                                    <th>Date certification</th>
                                    <th class="text-end">Montant</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($factures as $facture)
                                    <tr>
                                        <td>{{ Help::formatNumeroFacture($facture->numero) }}</td>
                                        <td>
                                            <span style="font-family: monospace; font-size: 0.9em;">
                                                {{ $facture->fne_reference }}
                                            </span>
                                            <span class="badge bg-success ms-1">Certifiée</span>
                                        </td>
                                        <td>
                                            @if($facture->service === 'LOCATION')
                                                @if($facture->location)
                                                    <span class="badge bg-info text-dark">Location</span> {{ $facture->location->numero }}
                                                @else — @endif
                                            @elseif($facture->commande)
                                                <a href="{{ route('orders.BECommande', ['numero' => $facture->commande->numero]) }}">
                                                    {{ $facture->commande->numero }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($facture->client)
                                                {{ $facture->client->nom }} {{ $facture->client->prenom }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($facture->fne_certified_at)
                                                {{ Carbon::parse($facture->fne_certified_at)->format('d/m/Y H:i') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            {{ number_format($facture->montant, 0, ',', ' ') }} fcfa
                                        </td>
                                        <td class="text-center">
                                            @if($facture->fne_token)
                                                <a href="{{ $facture->fne_token }}" target="_blank"
                                                    class="btn btn-outline-success btn-sm" title="Vérifier sur la plateforme FNE">
                                                    <i class="fas fa-qrcode"></i>
                                                    Vérifier
                                                </a>
                                            @endif
                                            @if($facture->service === 'LOCATION' && $facture->location)
                                                <a href="{{ route('orders.factureLocation', ['facture' => $facture->id, 'action' => 'voir']) }}"
                                                    class="btn btn-primary btn-sm" target="_blank">
                                                    <i class="fas fa-eye"></i> Voir
                                                </a>
                                                <a href="{{ route('orders.factureLocation', ['facture' => $facture->id, 'action' => 'telecharger']) }}"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @elseif($facture->service !== 'LOCATION' && $facture->commande)
                                                <a href="{{ route('show.actionFacture', ['commande' => $facture->commande, 'facture' => $facture, 'action' => 'voir', 'livraison' => 1]) }}"
                                                    class="btn btn-primary btn-sm" target="_blank">
                                                    <i class="fas fa-eye"></i> Voir
                                                </a>
                                                <a href="{{ route('show.actionFacture', ['commande' => $facture->commande, 'facture' => $facture, 'action' => 'telecharger', 'livraison' => 1]) }}"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            if ($('#tableFacturesValidees').length) {
                $('#tableFacturesValidees').DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[4, 'desc']],
                });
            }
        });
    </script>
@endsection
