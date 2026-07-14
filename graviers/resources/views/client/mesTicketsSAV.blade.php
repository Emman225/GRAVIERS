@php use Illuminate\Support\Carbon; @endphp

@extends('client.main')
@section('title', 'Mes tickets SAV')
@section('content')
@include('client.navMobile')
    <main class="main">
        @if (session('success'))
            <div class="alert alert-success text-center" id="notify">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger text-center">{{ session('error') }}</div>
        @endif

        <div class="page-header breadcrumb-wrap">
            <div class="container">
                <div class="breadcrumb">
                    <a href="{{ route('client.index') }}" rel="nofollow"><i class="fi-rs-home mr-5"></i>Accueil</a>
                    <span></span> Mon compte
                    <span></span> Mes tickets SAV
                </div>
            </div>
        </div>

        <div class="container mb-80 mt-50">
            <div class="row mb-20">
                <div class="col-lg-8">
                    <h1 class="heading-2 mb-10">Mes tickets SAV</h1>
                    <p class="text-muted">Suivez l'avancement de vos réclamations de service après-vente.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('client.ticketSAV') }}" class="btn"><i class="fi-rs-add mr-5"></i> Ouvrir un nouveau ticket</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr style="background-color:#1c57a3;color:#fff;">
                            <th>N° ticket</th>
                            <th>Produit</th>
                            <th>Objet</th>
                            <th>Date</th>
                            <th class="text-center">Statut</th>
                            <th>Solution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            @php
                                $st = (int) $ticket->statut;
                                $badge = $st === 3 ? 'badge-success bg-success' : ($st === 2 ? 'badge-warning bg-warning text-dark' : 'badge-secondary bg-secondary');
                                $libelle = $st === 3 ? 'Résolu' : ($st === 2 ? 'En traitement' : 'Nouveau');
                            @endphp
                            <tr>
                                <td>{{ $ticket->numero }}</td>
                                <td>{{ $ticket->detailCommande?->produit?->nom ?? '-' }}</td>
                                <td>{{ $ticket->objet }}</td>
                                <td>{{ Carbon::parse($ticket->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-center"><span class="badge rounded-pill {{ $badge }} text-white">{{ $libelle }}</span></td>
                                <td>
                                    @if ($st === 3 && $ticket->solution_trouvee)
                                        {{ $ticket->solution_trouvee }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Vous n'avez aucun ticket SAV pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
