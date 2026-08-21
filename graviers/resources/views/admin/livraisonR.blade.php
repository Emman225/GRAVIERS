@php
    use Illuminate\Support\carbon;
@endphp
@extends('layout.main')
@section('title','Livraison et réapprovisionnement')
@section('contenu')

<div class="content-header">
    <h2 class="content-title">État de livraison et réapprovisionnement</h2>
</div>
<div class="card mb-4">
    <header class="card-header">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <h5 class="mb-0">Produits à réapprovisionner</h5>
            @if($nbRuptures > 0)
                <span class="badge bg-danger">{{ $nbRuptures }} rupture{{ $nbRuptures > 1 ? 's' : '' }}</span>
            @endif
            <span class="badge bg-warning text-dark">{{ count($aReapprovisionner) - $nbRuptures }} sous le seuil</span>
        </div>
    </header>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle" id="reappro">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Fournisseur</th>
                        <th class="text-end">Quantité en stock</th>
                        <th class="text-end">Seuil d'alerte</th>
                        <th class="text-end">Manque</th>
                        <th class="text-center">État</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($aReapprovisionner as $ligne)
                        <tr>
                            <td>{{ $ligne->produit?->nom ?? '-' }}</td>
                            <td>{{ $ligne->fournisseur?->nom_prenoms ?? '-' }}</td>
                            <td class="text-end">{{ number_format((float) $ligne->qte, 0, ',', ' ') }}</td>
                            <td class="text-end">{{ number_format((float) $ligne->seuil_alert, 0, ',', ' ') }}</td>
                            <td class="text-end fw-bold">{{ number_format($ligne->manquePourAtteindreLeSeuil(), 0, ',', ' ') }}</td>
                            <td class="text-center">
                                @if($ligne->estEnRupture())
                                    <span class="badge bg-danger">Rupture</span>
                                @else
                                    <span class="badge bg-warning text-dark">Sous le seuil</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Aucun produit sous son seuil d'alerte.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-muted small mt-2 mb-0">
            Une ligne de stock par produit et par fournisseur. Un produit retiré du
            catalogue n'y figure pas : son stock n'est plus vendable.
        </p>
    </div>
</div>

<div class="card mb-4">
    <header class="card-header">
        <div class="row gx-3">
            <div class="col-md-12 me-auto d-flex">
                <div class="h3 me-5 fw-bold text-success">T. Qté enlevée : {{ number_format($enlevements->sum('qte'), 0, ',', ' ') }}</div>
                <div class="h3 ms-5 fw-bold text-success">T. Qté servie : {{ number_format($enlevements->sum(fn ($e) => (float) $e->qte_servi), 0, ',', ' ') }}</div>
            </div>
        </div>
    </header>
    <!-- card-header end// -->
    <div class="card-body">
        <div class="table-responsive">

            <table class="table table-striped" id="liste">
                <thead>
                    <tr>
                        <th class="text-center">Date</th>
                        <th class="text-center">N° commande</th>
                        <th class="text-center">N° BE</th>
                        <th class="text-center">Client</th>
                        <th class="text-center">N° Compte client</th>
                        <th class="text-center">Livreur</th>
                        <th class="text-center">N° compte livreur</th>
                        <th class="text-center">Fournisseur</th>
                        <th class="text-center">N° compte fournisseur</th>
                        <th class="text-center">Qté enlevée</th>
                        <th class="text-center">Qté livrée</th>
                        {{-- <th class="text-end">Action</th> --}}
                    </tr>
                </thead>

                <tbody>
                    @forelse ($enlevements as $enlevement)
                        <tr>
                            <td class="text-center">
                                <div class="info pl-3">
                                    <h6 class="mb-0 title">{{ Carbon::parse($enlevement->created_at)->format('d-m-Y'); }}</h6>
                                    {{-- <small class="text-muted">Login du fournisseur: {{$frs->user?->login}} </small> --}}
                                </div>
                            </td>
                            <td class="text-center"><span>{{$enlevement->livraison?->detailCommande?->commande?->numero}}</span></td>
                            <td class="text-center">{{$enlevement->code_enleve}}</td>
                            <td class="text-center">
                                {{$enlevement->livraison?->client?->display_name}}
                            </td>
                            <td class="text-center">{{$enlevement->livraison?->client?->user_id}}</td>
                            <td class="text-center">{{$enlevement->livreur?->user?->nom_prenoms}}</td>
                            <td class="text-center">{{$enlevement->livreur?->user?->id}}</td>
                            <td class="text-center">{{$enlevement->fournisseur?->nom_prenoms}}</td>
                            <td class="text-center">{{$enlevement->fournisseur?->user?->id}}</td>
                            <td class="text-center">{{ $enlevement->qte }}</td>
                            <td class="text-center">
                                {{-- Une cellule vide ne dit pas si le bon a ete servi a zero
                                     ou s'il ne l'est pas encore. --}}
                                {{ $enlevement->qte_servi !== null ? $enlevement->qte_servi : 'Non servi' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                Aucun bon d'enlèvement.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="container">
                <div class="row">


                </div>
            </div>
            <!-- table-responsive.// -->
        </div>
    </div>
    <!-- card-body end// -->
</div>

@endsection
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: {
                        url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                    },
                    order: [],
                });
            }
        });
    </script>
@endsection
