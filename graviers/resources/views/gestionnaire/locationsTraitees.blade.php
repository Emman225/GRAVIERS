@php use Illuminate\Support\Carbon; @endphp
@extends('layout.main')
@section('title','Locations traitées')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title card-title">Locations traitées</h2>
    </div>

    @if(session('success'))
        <div class="alert alert-success text-center">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="listeLocTraitees">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">N°</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Client</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Livreur</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Montant</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Caution</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">État</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Date retour</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Paiement</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($locations as $location)
                                    @php $etat = $location->etatLibelle(); @endphp
                                    <tr>
                                        <td class="text-center">{{ $location->numero }}</td>
                                        <td class="text-center"><b>{{ $location->client?->nom }} {{ $location->client?->prenom }}</b></td>
                                        <td class="text-center">{{ $location->livreur?->user?->nom_prenoms ?? '-' }}</td>
                                        <td class="text-center">{{ number_format($location->montant_total, 0, '', ' ') }} fcfa</td>
                                        <td class="text-center">
                                            {{ number_format($location->caution ?? 0, 0, '', ' ') }} fcfa
                                            @if (($location->caution_retenue ?? 0) > 0)
                                                <br><small class="text-danger">retenue : {{ number_format($location->caution_retenue, 0, '', ' ') }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill {{ $etat === 'EN COURS' ? 'bg-info text-dark' : 'bg-success' }}">{{ $etat }}</span>
                                        </td>
                                        <td class="text-center">{{ $location->date_retour ? Carbon::parse($location->date_retour)->format('d-m-Y') : '-' }}</td>
                                        <td class="text-center">
                                            @if ($location->statut == 3)
                                                <span class="badge rounded-pill alert-success text-success">Soldé</span>
                                            @elseif ($location->statut == 2)
                                                <span class="badge rounded-pill alert-success text-warning">En cours</span>
                                            @else
                                                <span class="badge rounded-pill alert-success text-danger">Aucun</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($etat === 'EN COURS')
                                                <a href="{{ route('show.retourLocationPage', $location) }}" class="btn btn-sm btn-success rounded font-sm">Retour matériel</a>
                                            @endif
                                            {{-- "Paiement" seulement si la location n'est pas déjà soldée (statut 3). --}}
                                            @if ($location->statut != 3)
                                                <a href="{{ route('paye.paiementLocation', $location) }}" class="btn btn-sm rounded font-sm">Paiement</a>
                                            @endif
                                            {{-- Facture FNE : générer (une fois) puis consulter. --}}
                                            @if ($location->factureFne)
                                                <a href="{{ route('orders.factureLocation', ['facture' => $location->factureFne->id, 'action' => 'voir']) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded font-sm">Voir facture</a>
                                            @else
                                                <form action="{{ route('orders.genererFactureLocation', $location) }}" method="post" style="display:inline-block">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded font-sm"
                                                        onclick="return confirm('Générer la facture FNE de cette location ?');">Générer facture</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
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
            $('#listeLocTraitees').DataTable({
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                order: [],
            });
        });
    </script>
@endsection
