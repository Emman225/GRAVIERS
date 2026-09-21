@extends('layout.main')
@section('title', 'Historique des enlèvements')
@section('contenu')
    <div class="screen-overlay"></div>
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Historique des enlèvements</h2>
            <p class="text-muted mb-0">Quantités enlevées par jour et par produit : les bons servis, à la date de leur validation. Seuls les produits ayant au moins un bon servi ont une colonne.</p>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            @include('fournisseur.partials._grilleEnlevements', [
                'grille'      => $grille,
                'tableId'     => 'historiqueEnlevements',
                'filename'    => 'historique-enlevements',
                'title'       => 'Historique des enlèvements',
                'routeFiltre' => route('sellers.historiqueEnlevements'),
                'servi'       => true,
            ])
        </div>
    </div>
@endsection
