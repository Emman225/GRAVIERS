{{-- LA GRILLE DU CLASSEUR (lot 83, 15/09/2026) : une ligne par jour, une colonne
     par produit, « Nb bons » et « Total quantités » ; « TOTAL PÉRIODE » en tête,
     comme la ligne 3 des feuilles PLANNING et HISTORIQUE.
     Attendu : $grille (PlanningFournisseur::grille), $tableId, $filename, $title, $routeFiltre, $servi. --}}
@php
    $fmt = fn ($n) => $n == 0 ? '' : rtrim(rtrim(number_format((float) $n, 2, ',', ' '), '0'), ',');
    $joursFr = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
@endphp
<form method="get" action="{{ $routeFiltre }}" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label class="form-label mb-1" for="debut">Date de début <span class="text-danger">*</span></label>
        <input type="date" class="form-control form-control-sm" id="debut" name="debut" required value="{{ $grille['debut']->toDateString() }}">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Afficher</button>
        <a href="{{ $routeFiltre }}" class="btn btn-sm btn-light">Aujourd'hui</a>
    </div>
    <div class="col-auto text-muted small">
        {{ \App\Services\PlanningFournisseur::JOURS }} jours, du {{ $grille['debut']->format('d/m/Y') }} au {{ $grille['fin']->format('d/m/Y') }}.
    </div>
</form>

<x-export-buttons :table-id="$tableId" :filename="$filename . '-du-' . $grille['debut']->toDateString()" :title="$title . ' du ' . $grille['debut']->format('d/m/Y') . ' au ' . $grille['fin']->format('d/m/Y')" />

<div class="table-responsive">
    <table class="table table-bordered table-sm" id="{{ $tableId }}">
        <thead>
            <tr>
                <th style="background-color: #1c57a3; color: white">Date</th>
                @foreach ($grille['produits'] as $p)
                    <th style="background-color: #1c57a3; color: white" class="text-end">{{ $p['nom'] }}{{ $p['unite'] !== '' ? ' (' . $p['unite'] . ')' : '' }}</th>
                @endforeach
                <th style="background-color: #1c57a3; color: white" class="text-end">Nb bons</th>
                <th style="background-color: #1c57a3; color: white" class="text-end">Total quantités</th>
            </tr>
        </thead>
        <tbody>
            <tr class="fw-bold table-light">
                <td>TOTAL PÉRIODE</td>
                @foreach ($grille['produits'] as $p)
                    <td class="text-end">{{ $fmt($grille['totaux'][$p['id']] ?? 0) }}</td>
                @endforeach
                <td class="text-end">{{ $grille['nbTotal'] ?: '' }}</td>
                <td class="text-end">{{ $fmt($grille['totalGeneral']) }}</td>
            </tr>
            @foreach ($grille['jours'] as $jour)
                <tr class="{{ $jour['nb'] > 0 ? '' : 'text-muted' }}">
                    <td data-order="{{ $jour['date']->toDateString() }}">{{ $joursFr[$jour['date']->dayOfWeek] }} {{ $jour['date']->format('d/m/Y') }}</td>
                    @foreach ($grille['produits'] as $p)
                        <td class="text-end">{{ $fmt($jour['quantites'][$p['id']] ?? 0) }}</td>
                    @endforeach
                    <td class="text-end">{{ $jour['nb'] ?: '' }}</td>
                    <td class="text-end">{{ $fmt($jour['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Le détail des bons de la période, comme la feuille BONS du classeur. --}}
<h5 class="mt-4">Bons de la période</h5>
<div class="table-responsive">
    <table class="table table-striped table-sm" id="{{ $tableId }}Bons">
        <thead>
            <tr>
                <th style="background-color: #1c57a3; color: white">Date</th>
                <th style="background-color: #1c57a3; color: white">N° bon</th>
                <th style="background-color: #1c57a3; color: white">{{ $servi ? 'Enlevé par' : 'Livreur / client' }}</th>
                <th style="background-color: #1c57a3; color: white">Produit</th>
                <th style="background-color: #1c57a3; color: white" class="text-end">Quantité</th>
                <th style="background-color: #1c57a3; color: white">Statut</th>
            </tr>
        </thead>
        <tbody>
            @php $aucun = true; @endphp
            @foreach ($grille['jours'] as $jour)
                @foreach ($jour['bons'] as $bon)
                    @php $aucun = false; @endphp
                    <tr>
                        <td data-order="{{ $jour['date']->toDateString() }}">{{ $jour['date']->format('d/m/Y') }}</td>
                        <td>{{ $bon->code_enleve ?: $bon->code_enlevement ?: $bon->id }}</td>
                        <td>{{ \App\Services\PlanningFournisseur::quiEnleve($bon) }}</td>
                        <td>{{ $bon->produit?->nom }}</td>
                        <td class="text-end">{{ $fmt(\App\Services\PlanningFournisseur::quantiteDuBon($bon, $servi)) ?: '0' }} {{ \App\Services\PlanningFournisseur::uniteDe($bon->produit) }}</td>
                        <td>{{ $servi ? 'Enlevé' : 'Prévu' }}</td>
                    </tr>
                @endforeach
            @endforeach
            @if ($aucun)
                <tr><td colspan="6" class="text-center text-muted">Aucun bon sur cette période.</td></tr>
            @endif
        </tbody>
    </table>
</div>
