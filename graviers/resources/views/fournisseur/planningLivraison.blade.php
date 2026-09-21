@extends('layout.main')
@section('title', 'Planning livraison')
@section('contenu')
    <div class="screen-overlay"></div>
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Planning livraison</h2>
            <p class="text-muted mb-0">Quantités à enlever par jour et par produit : les bons en attente, à la date de livraison prévue. Seuls les produits ayant au moins un bon en attente ont une colonne.</p>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            @include('fournisseur.partials._grilleEnlevements', [
                'grille'      => $grille,
                'tableId'     => 'planningLivraison',
                'filename'    => 'planning-livraison',
                'title'       => 'Planning livraison',
                'routeFiltre' => route('sellers.planningLivraison'),
                'servi'       => false,
            ])
        </div>
    </div>
@endsection
