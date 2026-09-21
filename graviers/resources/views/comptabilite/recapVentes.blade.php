@extends('layout.main')
@section('title','Récapitulatif des ventes')

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $tauxMarge = fn ($marge, $vente) => $vente > 0 ? round($marge / $vente * 100, 1) : null;
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Récapitulatif des ventes</h2>
    </div>

    {{-- LE BANDEAU MESURE LES VENTES, PAS LES ANOMALIES.

         Un bon qui ne retrouve plus sa ligne de commande n'a pas de prix de
         vente connu : on lui prêtait celui du catalogue, donc une recette
         jamais facturée, et cette fausse recette moins un coût bien réel
         écrasait la marge des vraies ventes. Ces bons restent dans le tableau
         ci-dessous, en tête, et sont annoncés à part. --}}
    @include('comptabilite._beneficeDalakoun', [
        'titre'          => 'Bénéfices de DALAKOUN sur les ventes',
        'produit'        => $totaux->venteRattachee,
        'charge'         => $totaux->coutRattache,
        'libelleProduit' => 'Vendu aux clients (HT)',
        'libelleCharge'  => 'Versé aux fournisseurs',
        'benefice'       => $totaux->margeRattachee,
        'note'           => "Sur la période affichée, ventes rattachées à leur commande. Le versement fournisseur est celui porté par les bons, à la quantité réellement servie. Hors taxes et hors transport : la marge du transport se lit sur « Bénéfices sur les livraisons ».",
    ])

    @if ($totaux->bonsOrphelins > 0)
        <div class="alert alert-warning">
            <strong>{{ $totaux->bonsOrphelins }} bon(s) d'enlèvement ne retrouvent plus leur ligne de commande</strong>,
            pour {{ number_format($totaux->coutOrphelins, 0, ',', ' ') }} fcfa versés aux fournisseurs.
            <br>
            Cette marchandise a été payée sans qu'on puisse dire à quelle vente elle correspond,
            ni à quel prix elle a été facturée. Lui prêter le prix du catalogue inventerait une
            recette : ces bons sont donc écartés du tableau et de tous les totaux de cet écran.
            Ils restent à traiter.
        </div>
    @endif

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
                    Ventes servies du {{ \Carbon\Carbon::parse($du)->format('d/m/Y') }}
                    au {{ \Carbon\Carbon::parse($au)->format('d/m/Y') }}
                </span>
            </div>
        </form>

        <header class="card-header">
            <div class="row gx-3 text-center">
                <div class="col-6 col-md">
                    <div class="text-muted small">Ventes</div>
                    {{-- La ligne des bons sans commande n est pas une vente. --}}
                    <div class="h5 mb-0">{{ collect($lignes)->where('orphelin', '!=', true)->count() }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Montant vendu HT</div>
                    <div class="h5 mb-0 text-success">{{ $fmt($totaux->vente) }} fcfa</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Coût fournisseur</div>
                    <div class="h5 mb-0 text-danger">{{ $fmt($totaux->cout) }} fcfa</div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Marge</div>
                    <div class="h5 mb-0 {{ $totaux->marge >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $fmt($totaux->marge) }} fcfa
                        @if ($tauxMarge($totaux->marge, $totaux->vente) !== null)
                            <small class="text-muted">({{ $tauxMarge($totaux->marge, $totaux->vente) }} %)</small>
                        @endif
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="text-muted small">Reste à encaisser</div>
                    <div class="h5 mb-0 {{ $totaux->reste > 0 ? 'text-warning' : 'text-muted' }}">
                        {{ $fmt($totaux->reste) }} fcfa
                    </div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste"
                              filename="recapitulatif-ventes"
                              title="Récapitulatif des ventes" />
            <div class="table-responsive">
                <table class="table table-striped align-middle" id="liste">
                    <thead>
                        <tr>
                            <th style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Commande</th>
                            <th style="background-color: #1c57a3; color: white;">Date</th>
                            <th style="background-color: #1c57a3; color: white;">Client</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Vendu HT</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Coût fournisseur</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Marge</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Encaissé</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Reste dû</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr @class(['table-warning' => !empty($ligne->orphelin)])>
                                <td>
                                    {{ $ligne->numero ?: '—' }}
                                    @if (!empty($ligne->orphelin))
                                        <br><small class="text-muted">{{ $ligne->lignes }} bon{{ $ligne->lignes > 1 ? 's' : '' }} servi{{ $ligne->lignes > 1 ? 's' : '' }} sans ligne de commande</small>
                                    @endif
                                </td>
                                <td>{{ $ligne->date ? \Help::dateHeure($ligne->date) : '—' }}</td>
                                <td>{{ $ligne->client }}</td>
                                <td class="text-end">{{ $fmt($ligne->vente) }}</td>
                                <td class="text-end text-danger">{{ $fmt($ligne->cout) }}</td>
                                <td class="text-end {{ $ligne->marge >= 0 ? 'text-success' : 'text-danger fw-bold' }}">
                                    {{ $fmt($ligne->marge) }}
                                    @if ($tauxMarge($ligne->marge, $ligne->vente) !== null)
                                        <br><small class="text-muted">{{ $tauxMarge($ligne->marge, $ligne->vente) }} %</small>
                                    @endif
                                </td>
                                <td class="text-end">{{ $fmt($ligne->encaisse) }}</td>
                                <td class="text-end {{ $ligne->reste > 0 ? 'text-warning fw-bold' : 'text-muted' }}">
                                    {{ $fmt($ligne->reste) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    Aucune vente servie sur cette période.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (count($lignes))
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3">Total</td>
                                <td class="text-end">{{ $fmt($totaux->vente) }}</td>
                                <td class="text-end text-danger">{{ $fmt($totaux->cout) }}</td>
                                <td class="text-end {{ $totaux->marge >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $fmt($totaux->marge) }}
                                </td>
                                <td class="text-end">{{ $fmt($totaux->encaisse) }}</td>
                                <td class="text-end">{{ $fmt($totaux->reste) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Une ligne par commande, rattachée à la date à laquelle le fournisseur a servi
                le bon — même axe de temps que « CA par famille » et « CA détaillé », pour que
                les trois écrans annoncent le même chiffre d'affaires. Le coût fournisseur est
                celui porté par les bons, à la quantité réellement servie. Montants hors taxes
                et hors transport.
                <br>
                Les locations n'y figurent pas : une location n'est pas une vente, et se lit sur
                son propre état. Un bon dont la livraison ne pointe plus vers sa ligne de commande
                n'a pas de prix de vente connu ; il est écarté des totaux et annoncé en haut de
                page. Les trois écrans du chiffre d'affaires appliquent la même règle.
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
