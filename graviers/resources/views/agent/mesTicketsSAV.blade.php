@php use Illuminate\Support\Carbon; @endphp
@extends('layout.main')
@section('title','Mes tickets SAV')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Mes tickets SAV</h2>
        <p class="text-muted">Réclamations qui vous sont assignées. Traitez-les puis clôturez avec la solution apportée.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead style="background-color:#1c57a3;color:#fff;">
                        <tr>
                            <th>N° ticket</th>
                            <th>Client</th>
                            <th>Produit concerné</th>
                            <th>Objet</th>
                            <th>Date</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            @php
                                $st = (int) $ticket->statut;
                                $badge = $st === 3 ? 'bg-success' : ($st === 2 ? 'bg-warning text-dark' : 'bg-secondary');
                                $libelle = $st === 3 ? 'Résolu' : ($st === 2 ? 'À traiter' : 'Nouveau');
                            @endphp
                            <tr>
                                <td>{{ $ticket->numero }}</td>
                                <td>{{ $ticket->client?->nom }} {{ $ticket->client?->prenom }}</td>
                                <td>{{ $ticket->detailCommande?->produit?->nom ?? '-' }}</td>
                                <td>{{ $ticket->objet }}</td>
                                <td>{{ Carbon::parse($ticket->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-center"><span class="badge rounded-pill {{ $badge }}">{{ $libelle }}</span></td>
                                <td class="text-center">
                                    @if ($st === 3)
                                        <a href="{{ route('show.traiterTicketSAVPage', $ticket) }}" class="btn btn-sm btn-outline-secondary rounded font-sm">Voir</a>
                                    @else
                                        <a href="{{ route('show.traiterTicketSAVPage', $ticket) }}" class="btn btn-sm btn-primary rounded font-sm">Traiter</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
