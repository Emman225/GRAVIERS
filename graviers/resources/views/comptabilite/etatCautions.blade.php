@extends('layout.main')
@section('title','État des cautions de location')

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">État des cautions de location</h2>
    </div>

    {{-- CE QUE CET ÉTAT DIT, ET CE QU'IL NE DIT PAS.
         Une caution n'est pas un produit : c'est un dépôt que l'entreprise
         détient et doit rendre. Seule la RETENUE lui reste acquise et se
         déclare ; le RESTITUÉ éteint une dette. Le DÉTENU, lui, n'a pas
         bougé — le confondre avec du restitué gonflerait les sorties. --}}
    <div class="alert alert-light border mb-4">
        <strong>Comment lire cet état.</strong>
        La <strong>retenue</strong> reste acquise à DALAKOUN — dommage, retard —&nbsp;:
        c'est un produit, il se déclare. Le <strong>montant restitué</strong> repart chez
        le client&nbsp;: c'est une dette éteinte. Le <strong>détenu</strong> concerne les
        locations dont le matériel n'est pas encore rendu&nbsp;: cette caution n'a ni été
        retenue ni rendue, elle est simplement gardée.
        <br>
        La date retenue est celle du <strong>retour du matériel</strong>, moment où la
        caution se dénoue. Les locations non rendues apparaissent sur leur date de
        location, pour que le détenu de la période soit visible.
    </div>

    <div class="card mb-4">
        {{-- Formulaire en GET : pas de @csrf, le jeton se retrouverait dans l'URL. --}}
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
            </div>
            <div class="col-md-3 col-lg-5 pt-4 text-md-end">
                <span class="text-muted small">
                    Du {{ \Carbon\Carbon::parse($du)->format('d/m/Y') }}
                    au {{ \Carbon\Carbon::parse($au)->format('d/m/Y') }}
                </span>
            </div>
        </form>

        <header class="card-header">
            <div class="row gx-3 text-center">
                <div class="col-6 col-md">
                    <div class="text-muted small">Locations</div>
                    <div class="h5 mb-0">{{ count($lignes) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Cautions encaissées</div>
                    <div class="h5 mb-0">{{ $fmt($totalCaution) }} fcfa</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Retenue sur caution</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totalRetenue) }} fcfa</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Montant restitué au client</div>
                    <div class="h5 mb-0 text-primary">{{ $fmt($totalRestitue) }} fcfa</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Encore détenue</div>
                    <div class="h5 mb-0 text-warning">{{ $fmt($totalDetenue) }} fcfa</div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="listeCautions"
                              filename="etat-des-cautions"
                              title="État des cautions de location" />
            <div class="table-responsive">
                <table id="listeCautions" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="background-color:#1c57a3;color:#fff;">N° location</th>
                            <th style="background-color:#1c57a3;color:#fff;">Client</th>
                            <th style="background-color:#1c57a3;color:#fff;">Retour</th>
                            <th style="background-color:#1c57a3;color:#fff;">État</th>
                            <th class="text-end" style="background-color:#1c57a3;color:#fff;">Caution</th>
                            <th class="text-end" style="background-color:#1c57a3;color:#fff;">Retenue</th>
                            <th class="text-end" style="background-color:#1c57a3;color:#fff;">Restitué</th>
                            <th class="text-end" style="background-color:#1c57a3;color:#fff;">Détenue</th>
                            <th style="background-color:#1c57a3;color:#fff;">Motif de la retenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr>
                                <td>{{ $l['numero'] }}</td>
                                <td>{{ $l['client'] }}</td>
                                <td>
                                    {{ $l['date_retour']
                                        ? \Help::dateHeure($l['date_retour'])
                                        : '—' }}
                                </td>
                                <td>{{ $l['etat'] }}</td>
                                <td class="text-end">{{ $fmt($l['caution']) }}</td>
                                <td class="text-end {{ $l['retenue'] > 0 ? 'text-success fw-bold' : 'text-muted' }}">
                                    {{ $fmt($l['retenue']) }}
                                </td>
                                <td class="text-end {{ $l['restitue'] > 0 ? 'text-primary' : 'text-muted' }}">
                                    {{ $fmt($l['restitue']) }}
                                </td>
                                <td class="text-end {{ $l['detenue'] > 0 ? 'text-warning fw-bold' : 'text-muted' }}">
                                    {{ $fmt($l['detenue']) }}
                                </td>
                                <td>
                                    {{-- Une retenue sans motif est une retenue que personne ne
                                         pourra justifier au client. On la signale. --}}
                                    @if ($l['retenue'] > 0 && !$l['motif'])
                                        <span class="text-danger">Aucun motif enregistré</span>
                                    @else
                                        {{ $l['motif'] ?: '—' }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    Aucune caution sur cette période.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (count($lignes) > 0)
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">TOTAL</td>
                                <td class="text-end">{{ $fmt($totalCaution) }}</td>
                                <td class="text-end text-success">{{ $fmt($totalRetenue) }}</td>
                                <td class="text-end text-primary">{{ $fmt($totalRestitue) }}</td>
                                <td class="text-end text-warning">{{ $fmt($totalDetenue) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@endsection
