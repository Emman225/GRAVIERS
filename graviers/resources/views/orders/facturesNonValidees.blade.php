@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title', 'Factures non validées')

@section('contenu')
    <section class="content-main">
        <div class="content-header">
            <h2 class="content-title">Factures non validées (en attente de certification DGI)</h2>
        </div>

        <div class="card">
            <div class="card-body">

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('warning'))
                    <div class="alert alert-warning">{{ session('warning') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if($factures->isEmpty())
                    <h4 class="text-center my-4 text-muted">
                        Aucune facture en attente de validation FNE pour le moment.
                    </h4>
                @else
                    <x-export-buttons table-id="tableFacturesNonValidees" filename="factures-non-validees" title="Factures non validées" />
                    <div class="table-responsive">
                        <table class="table table-striped" id="tableFacturesNonValidees">
                            <thead style="background-color: #1c57a3; color: white;">
                                <tr>
                                    <th>N° Facture</th>
                                    <th>Commande / Location</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th class="text-end">Montant</th>
                                    <th>Statut FNE</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($factures as $facture)
                                    <tr>
                                        <td>{{ Help::formatNumeroFacture($facture->numero) }}</td>
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
                                        <td>{{ Carbon::parse($facture->created_at)->format('d/m/Y H:i') }}</td>
                                        <td class="text-end">
                                            {{ number_format($facture->montant, 0, ',', ' ') }} fcfa
                                        </td>
                                        <td>
                                            @switch($facture->fne_status)
                                                @case('failed')
                                                    <span class="badge bg-danger" title="{{ $facture->fne_error_message }}">
                                                        Échec FNE
                                                    </span>
                                                    @break
                                                @case('disabled')
                                                    <span class="badge bg-secondary" title="Module FNE désactivé">
                                                        Non certifiée
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="badge bg-warning text-dark">En attente</span>
                                            @endswitch
                                        </td>
                                        <td class="text-center">
                                            <form action="{{ route('orders.validerFactureFne', $facture) }}"
                                                method="post" style="display:inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm"
                                                    onclick="return confirm('Confirmer la validation de cette facture auprès de la DGI (FNE) ?');">
                                                    <i class="material-icons md-check align-middle"></i>
                                                    Valider
                                                </button>
                                            </form>

                                            @if($facture->service === 'LOCATION' && $facture->location)
                                                <a href="{{ route('orders.factureLocation', ['facture' => $facture->id, 'action' => 'voir']) }}"
                                                    class="btn btn-primary btn-sm" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            @elseif($facture->service !== 'LOCATION' && $facture->commande)
                                                <a href="{{ route('show.actionFacture', ['commande' => $facture->commande, 'facture' => $facture, 'action' => 'voir', 'livraison' => 1]) }}"
                                                    class="btn btn-primary btn-sm" target="_blank">
                                                    <i class="fas fa-eye"></i>
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
            if ($('#tableFacturesNonValidees').length) {
                $('#tableFacturesNonValidees').DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[3, 'desc']],
                });
            }
        });
    </script>
@endsection
