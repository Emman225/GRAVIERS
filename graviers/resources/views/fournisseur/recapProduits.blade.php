@extends('layout.main')
@section('title', 'Récap produits')
@section('contenu')
    <div class="screen-overlay"></div>
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Récapitulatif par produit</h2>
            <p class="text-muted mb-0">Sur tout ce que porte le site : enlevé (bons servis), prévu (bons en attente), stock disponible et reste à enlever.</p>
        </div>
    </div>
    @php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ' '), '0'), ','); @endphp
    <div class="card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="recapProduits" filename="recap-produits" title="Récapitulatif par produit" />
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="recapProduits">
                    <thead>
                        <tr>
                            <th style="background-color: #1c57a3; color: white">Produit</th>
                            <th style="background-color: #1c57a3; color: white">Unité</th>
                            <th style="background-color: #1c57a3; color: white" class="text-end">Quantité totale à enlever</th>
                            <th style="background-color: #1c57a3; color: white" class="text-end">Enlevé (cumul)</th>
                            <th style="background-color: #1c57a3; color: white" class="text-end">Prévu (à venir)</th>
                            <th style="background-color: #1c57a3; color: white" class="text-end">Stock disponible</th>
                            <th style="background-color: #1c57a3; color: white" class="text-end">Reste à enlever</th>
                            <th style="background-color: #1c57a3; color: white" class="text-end">% enlevé</th>
                            <th style="background-color: #1c57a3; color: white" class="text-end">Nb bons enlevés</th>
                            <th style="background-color: #1c57a3; color: white" class="text-end">Nb bons prévus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr>
                                <td>{{ $l['produit'] }}</td>
                                <td>{{ $l['unite'] }}</td>
                                <td class="text-end">{{ $fmt($l['total']) }}</td>
                                <td class="text-end">{{ $fmt($l['enleve']) }}</td>
                                <td class="text-end">{{ $fmt($l['prevu']) }}</td>
                                <td class="text-end">{{ $fmt($l['stock']) }}</td>
                                <td class="text-end">{{ $fmt($l['reste']) }}</td>
                                <td class="text-end">{{ $l['pourcentage'] === null ? '-' : $fmt($l['pourcentage']) . ' %' }}</td>
                                <td class="text-end">{{ $l['nbEnleves'] }}</td>
                                <td class="text-end">{{ $l['nbPrevus'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted">Aucun produit.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mt-2 mb-0">
                Quantité totale à enlever = stock disponible + prévu + enlevé ; reste à enlever = stock disponible + prévu ;
                % enlevé = enlevé / quantité totale. Le stock disponible est celui de la page Stock, déjà diminué des bons réservés.
            </p>
        </div>
    </div>
@endsection
