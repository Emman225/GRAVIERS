@extends('layout.main')
@section('title','Chiffre d\'affaire détaillé')

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Chiffre d'affaire détaillé</h2>
    </div>

    <div class="card mb-4">
        {{-- Pas de @csrf ici : le formulaire est en GET, le jeton n'y sert à rien
             et se retrouvait affiché dans l'URL. --}}
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
                    <a href="{{ route('show.CADetaille') }}" class="btn btn-light">Tout l'historique</a>
                @endif
            </div>
            <div class="col-md-3 col-lg-5 pt-4 text-md-end">
                {{-- Les champs affichaient « du 1er janvier à aujourd'hui » alors que
                     le tableau montrait tout l'historique : l'écran mentait sur sa
                     propre période. Ils reflètent maintenant ce qui est réellement
                     affiché. --}}
                <span class="text-muted small">
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
                    <div class="text-muted small">Quantité demandée</div>
                    <div class="h5 mb-0">{{ $fmt($totalQteDemandee) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Quantité servie</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totalQteServie) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Montant vendu HT</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totalVente) }} fcfa</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Coût fournisseur HT</div>
                    <div class="h5 mb-0">{{ $fmt($totalCout) }} fcfa</div>
                </div>
                <div class="col-12 col-md">
                    <div class="text-muted small">Marge brute HT</div>
                    <div class="h5 mb-0 {{ $totalMarge < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $fmt($totalMarge) }} fcfa
                    </div>
                </div>
            </div>
        </header>

        <div class="card-body">
            {{-- CE QUI A ÉTÉ MIS DE CÔTÉ, ET POURQUOI.

                 Un chiffre qui disparaît sans un mot est un chiffre qu'on croit
                 perdu. Ces deux ensembles ne sont pas des ventes ; ils sont
                 nommés, comptés, et renvoyés là où ils se lisent. --}}
            @if (($bonsDeLocation ?? 0) > 0 || ($sansPrix['bons'] ?? 0) > 0)
                <div class="alert alert-warning">
                    @if (($bonsDeLocation ?? 0) > 0)
                        <div>
                            <strong>{{ $bonsDeLocation }} bon(s) de location</strong> ne sont pas comptés ici :
                            une location n'est pas une vente. Son coût est un tarif journalier
                            multiplié par le nombre de jours, sans prix de vente en face.
                            Elles se lisent sur l'état des locations.
                        </div>
                    @endif
                    @if (($sansPrix['bons'] ?? 0) > 0)
                        <div @class(['mt-2' => ($bonsDeLocation ?? 0) > 0])>
                            <strong>{{ $sansPrix['bons'] }} bon(s) ne retrouvent plus leur ligne de commande</strong>,
                            pour {{ $fmt($sansPrix['qte']) }} en quantité servie et
                            {{ $fmt($sansPrix['cout']) }} fcfa versés aux fournisseurs.
                            Le prix facturé au client est inconnu : lui prêter celui du catalogue
                            inventerait une recette. Ils sont donc écartés du tableau et des totaux,
                            et restent à traiter.
                        </div>
                    @endif
                </div>
            @endif

            <x-export-buttons table-id="liste" filename="chiffre-d-affaire-detaille" title="Etat chiffre d'affaire détaillé" />

            <div class="table-responsive">
                <table class="table table-striped align-middle" id="liste">
                    <thead>
                        {{-- Le <tr> manquait : les <th> flottaient directement dans le
                             <thead>, ce que DataTables et l'export ne lisent pas de
                             la même façon selon les navigateurs. --}}
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Désignation</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Famille</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité demandée</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité servie</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité dispo</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Montant vendu HT</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Coût fournisseur HT</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Marge brute HT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stats as $s)
                            <tr>
                                <td>{{ $s->nom }}</td>
                                <td class="text-center">{{ $s->categories }}</td>
                                <td class="text-end">{{ $fmt($s->qteDemandee) }}</td>
                                <td class="text-end">
                                    {{ $fmt($s->qteServie) }}
                                    @if($s->qteServie < $s->qteDemandee)
                                        {{-- L'écart entre demandé et servi est l'information
                                             que l'ancien écran ne montrait nulle part. --}}
                                        <br><small class="text-danger">
                                            &minus;{{ $fmt($s->qteDemandee - $s->qteServie) }} non servi
                                        </small>
                                    @endif
                                </td>
                                <td class="text-end">{{ $fmt($s->dispo) }}</td>
                                <td class="text-end">{{ $fmt($s->vente) }} fcfa</td>
                                <td class="text-end">{{ $fmt($s->cout) }} fcfa</td>
                                <td class="text-end fw-bold {{ $s->marge < 0 ? 'text-danger' : '' }}">
                                    {{ $fmt($s->marge) }} fcfa
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    Aucun bon servi sur cette période.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if(count($stats))
                        <tfoot>
                            <tr style="background-color:#1c57a3; color:white">
                                <td colspan="2" class="fw-bold text-end">Total</td>
                                <td class="text-end fw-bold">{{ $fmt($totalQteDemandee) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totalQteServie) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totalDispo) }}</td>
                                <td class="text-end fw-bold">{{ $fmt($totalVente) }} fcfa</td>
                                <td class="text-end fw-bold">{{ $fmt($totalCout) }} fcfa</td>
                                <td class="text-end fw-bold">{{ $fmt($totalMarge) }} fcfa</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Seuls les bons validés par le fournisseur sont comptés, à la quantité
                réellement servie. Le coût fournisseur est celui du bon lui-même, c'est-à-dire
                ce qui est réellement dû au fournisseur. Montants hors taxes et hors transport,
                le transport étant facturé pour le compte de l'entreprise.
            </p>
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
            // Sur une table vide, le corps ne contient qu'une ligne à colspan :
            // DataTables la prend pour une ligne de données et lève
            // « Requested unknown parameter ». On ne l'initialise pas.
            var $corps = $('#liste tbody');
            if ($corps.find('td[colspan]').length) {
                return;
            }

            $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                columnDefs: [{ targets: '_all', defaultContent: '-' }],
            });
        });
    </script>
@endsection
