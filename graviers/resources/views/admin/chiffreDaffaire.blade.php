@extends('layout.main')
@section('title','Chiffre d\'affaire par famille')

@php
    // Un seul format de nombre sur toute la page. Le bandeau écrivait 603.342
    // pendant que les colonnes écrivaient 603 342, sur le même écran.
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Chiffre d'affaire par famille</h2>
    </div>

    <div class="card mb-4">
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
                @if($du || $au)
                    <a href="{{ route('show.CAParFamille') }}" class="btn btn-light">Tout l'historique</a>
                @endif
            </div>
            <div class="col-md-3 col-lg-5 pt-4 text-md-end">
                <span class="text-muted small">
                    {{-- Le chiffre d'affaires est daté du jour où le fournisseur a servi le bon,
                         et non du jour où la commande a été saisie. --}}
                    @if($du || $au)
                        Bons servis {{ $du ? 'du ' . \Carbon\Carbon::parse($du)->format('d/m/Y') : '' }}
                        {{ $au ? 'au ' . \Carbon\Carbon::parse($au)->format('d/m/Y') : '' }}
                    @else
                        Tout l'historique des bons servis
                    @endif
                </span>
            </div>
        </form>

        <header class="card-header">
            <div class="row gx-3 text-center">
                <div class="col-6 col-md">
                    <div class="text-muted small">Quantité en stock</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($qteTotal) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Quantité vendue</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($qteVendue) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Montant HT</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totalHt) }} fcfa</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">TVA {{ $fmt($tauxTva) }} %</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totalTva) }} fcfa</div>
                </div>
                <div class="col-12 col-md">
                    <div class="text-muted small">Montant TTC</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totalTtc) }} fcfa</div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle" id="liste">
                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Désignation</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Familles</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité vendue</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Montant HT</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">TVA</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Montant TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($familles as $famille)
                            {{-- Bandeau de famille : l'écran s'appelle « par famille », il
                                 regroupe donc par famille. --}}
                            <tr class="table-light">
                                <td colspan="7" class="fw-bold text-uppercase" style="letter-spacing:.04em">
                                    {{ $famille['nom'] }}
                                    <span class="text-muted fw-normal text-lowercase">
                                        &mdash; {{ count($famille['lignes']) }} produit{{ count($famille['lignes']) > 1 ? 's' : '' }}
                                    </span>
                                </td>
                            </tr>

                            @foreach($famille['lignes'] as $ligne)
                                <tr>
                                    <td>{{ $ligne->nom }}</td>
                                    {{-- Un produit peut relever de plusieurs familles : on les cite
                                         toutes ici, alors qu'il n'est compté que dans la première,
                                         pour que les sous-totaux s'additionnent sans doublon. --}}
                                    <td class="text-center">{{ $ligne->familles }}</td>
                                    <td class="text-end">{{ $fmt($ligne->qte) }}</td>
                                    <td class="text-end">{{ $fmt($ligne->qteVendue) }}</td>
                                    <td class="text-end">{{ $fmt($ligne->ht) }} fcfa</td>
                                    <td class="text-end">{{ $fmt($ligne->tva) }} fcfa</td>
                                    <td class="text-end">{{ $fmt($ligne->ttc) }} fcfa</td>
                                </tr>
                            @endforeach

                            <tr style="border-top:2px solid #1c57a3">
                                <td colspan="2" class="fw-bold text-end">Sous-total {{ $famille['nom'] }}</td>
                                <td class="text-end fw-bold">{{ $fmt($famille['qte']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($famille['qteVendue']) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($famille['ht']) }} fcfa</td>
                                <td class="text-end fw-bold">{{ $fmt($famille['tva']) }} fcfa</td>
                                <td class="text-end fw-bold">{{ $fmt($famille['ttc']) }} fcfa</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    Aucun bon servi sur cette période.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if(count($familles))
                        <tfoot>
                            <tr style="background-color:#1c57a3; color:white">
                                <td colspan="2" class="fw-bold text-end">Total général</td>
                                <td class="text-end fw-bold">{{ $fmt($qteTotal) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($qteVendue) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totalHt) }} fcfa</td>
                                <td class="text-end fw-bold">{{ $fmt($totalTva) }} fcfa</td>
                                <td class="text-end fw-bold">{{ $fmt($totalTtc) }} fcfa</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Seuls les bons validés par le fournisseur sont comptés, à la quantité
                réellement servie et au prix facturé au client. Un produit relevant de
                plusieurs familles n'est compté que dans la première, afin que les
                sous-totaux s'additionnent exactement au total général.
            </p>
        </div>
    </div>
@endsection
