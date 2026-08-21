@extends('layout.main')
@section('title','État de parrainage')

@php
    $fmt = fn ($v) => Help::formatNombre($v, true);
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">État de parrainage</h2>
    </div>

    <div class="card mb-4">
        {{-- Le filtre était entièrement en commentaire, ici comme dans la requête :
             le contrôleur calculait une période que personne n'appliquait. --}}
        <form method="GET" action="" class="row gx-3 p-3 align-items-center">
            <div class="col-md-3 col-lg-2">
                <label for="du" class="form-label mb-1 small text-muted">Du</label>
                <input type="date" class="form-control" name="du" id="du" value="{{ $du }}">
            </div>
            <div class="col-md-3 col-lg-2">
                <label for="au" class="form-label mb-1 small text-muted">Au</label>
                <input type="date" class="form-control" name="au" id="au" value="{{ $au }}">
            </div>
            <div class="col-md-6 col-lg-4 pt-4">
                <button type="submit" class="btn btn-primary">Rechercher</button>
                @if($du || $au)
                    <a href="{{ route('show.etatParrainage') }}" class="btn btn-light">Tout l'historique</a>
                @endif
            </div>
            <div class="col-lg-4 pt-4 text-lg-end">
                <span class="text-muted small">
                    @if($du || $au)
                        Paiements {{ $du ? 'du ' . \Carbon\Carbon::parse($du)->format('d/m/Y') : '' }}
                        {{ $au ? 'au ' . \Carbon\Carbon::parse($au)->format('d/m/Y') : '' }}
                    @else
                        Tout l'historique des paiements
                    @endif
                </span>
            </div>
        </form>

        <header class="card-header">
            <div class="row gx-3 text-center">
                <div class="col-4">
                    <div class="text-muted small">Apporteurs</div>
                    <div class="h5 mb-0 text-success">{{ count($apporteurs) }}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Filleuls ayant payé</div>
                    <div class="h5 mb-0 text-success">{{ $nbFilleuls }}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Total encaissé</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totalGeneral) }}</div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="etat-de-parrainage" title="Etat de parrainage" />
            <div class="table-responsive">
                <table class="table table-striped align-middle" id="liste">
                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Code apporteur</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Apporteur d'affaires</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Client filleul</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Total payé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($apporteurs as $a)
                            @foreach ($a->clients as $c)
                                <tr>
                                    <td class="text-center">{{ $a->code }}</td>
                                    <td>{{ $a->nom }}</td>
                                    <td>{{ $c->client }}</td>
                                    <td class="text-end">{{ $fmt($c->total) }}</td>
                                </tr>
                            @endforeach

                            {{-- La question posée à cet écran est « combien chaque apporteur
                                 a-t-il fait entrer » : il n'y répondait pas. --}}
                            <tr style="border-top:2px solid #1c57a3">
                                <td colspan="3" class="text-end fw-bold">
                                    Sous-total {{ $a->nom }}
                                    <span class="text-muted fw-normal">
                                        &mdash; {{ count($a->clients) }} filleul{{ count($a->clients) > 1 ? 's' : '' }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold">{{ $fmt($a->total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    Aucun paiement de filleul sur cette période.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if(count($apporteurs))
                        <tfoot>
                            <tr style="background-color:#1c57a3; color:white">
                                <td colspan="3" class="fw-bold text-end">Total général</td>
                                <td class="text-end fw-bold">{{ $fmt($totalGeneral) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Seuls les paiements validés sont comptés. Un client n'apparaît que s'il est
                rattaché à un apporteur et qu'il a effectivement payé sur la période.
            </p>
        </div>
    </div>
@endsection
