@php use Illuminate\Support\Carbon; $resolu = (int) $ticket->statut === 3; @endphp
@extends('layout.main')
@section('title','Traiter un ticket SAV')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Ticket SAV {{ $ticket->numero }}</h2>
        <a href="{{ route('show.mesTicketsSAV') }}" class="btn btn-outline-secondary btn-sm">Retour à mes tickets</a>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Client :</strong> {{ $ticket->client?->nom }} {{ $ticket->client?->prenom }}</p>
                    <p class="mb-1"><strong>Produit concerné :</strong> {{ $ticket->detailCommande?->produit?->nom ?? '-' }}</p>
                    <p class="mb-1"><strong>Ouvert le :</strong> {{ Carbon::parse($ticket->created_at)->format('d/m/Y H:i') }}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><strong>Statut :</strong>
                        <span class="badge {{ $resolu ? 'bg-success' : 'bg-warning text-dark' }}">{{ $resolu ? 'Résolu' : 'À traiter' }}</span>
                    </p>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><strong>Objet</strong></label>
                <div class="form-control bg-light">{{ $ticket->objet }}</div>
            </div>
            <div class="mb-4">
                <label class="form-label"><strong>Message du client</strong></label>
                <div class="form-control bg-light" style="min-height:90px; white-space:pre-wrap;">{{ $ticket->message }}</div>
            </div>

            @if ($resolu)
                <div class="mb-2">
                    <label class="form-label"><strong>Solution apportée</strong></label>
                    <div class="form-control bg-light" style="min-height:90px; white-space:pre-wrap;">{{ $ticket->solution_trouvee }}</div>
                </div>
                <span class="text-success"><i class="material-icons md-check align-middle"></i> Ticket clôturé.</span>
            @else
                <form method="POST" action="{{ route('show.traiterTicketSAV', $ticket) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label"><strong>Solution apportée au client</strong> <span class="text-danger">*</span></label>
                        <textarea name="solution" rows="5" class="form-control" required placeholder="Décrivez la résolution du problème…">{{ old('solution') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-success"
                        onclick="return confirm('Clôturer ce ticket comme résolu ?');">
                        <i class="material-icons md-check align-middle"></i> Clôturer (résolu)
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
