@extends('layout.main')
@section('title','Facturation du livreur')

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $nb  = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Facturation — {{ $livreur->user->nom_prenoms ?? 'Livreur n° ' . $livreur->id }}</h2>
    </div>

    {{-- Clés propres : Flasher capte success/error et les rejoue en bulle
         flottante, qui s'efface d'elle-même. --}}
    @if (session('succes_grille'))
        <div class="alert alert-success">{{ session('succes_grille') }}</div>
    @endif
    @if (session('erreur_grille'))
        <div class="alert alert-danger">{{ session('erreur_grille') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $erreur)
                <div>{{ $erreur }}</div>
            @endforeach
        </div>
    @endif

    @php
        $couverture = $tarifs->count();
        $marges     = $tarifs->map(fn ($t) => $t->tauxMarge())->filter(fn ($v) => $v !== null);
    @endphp

    <div class="card mb-4 {{ $couverture ? 'border-success' : 'border-warning' }}">
        <div class="card-body">
            <div class="row gx-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="text-muted small">Tranches renseignées</div>
                    <div class="h4 mb-0">{{ $couverture }} / {{ $trancheClient }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">Part du livreur</div>
                    <div class="h4 mb-0">
                        {{ $livreur->part_grille ? $nb($livreur->part_grille) . ' %' : '—' }}
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">Marge DALAKOUN la plus faible</div>
                    <div class="h4 mb-0 {{ $marges->min() !== null && $marges->min() < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $marges->min() !== null ? $nb($marges->min()) . ' %' : '—' }}
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">Marge la plus élevée</div>
                    <div class="h4 mb-0 text-success">
                        {{ $marges->max() !== null ? $nb($marges->max()) . ' %' : '—' }}
                    </div>
                </div>
            </div>

            @if ($couverture < $trancheClient)
                <div class="alert alert-warning mt-3 mb-0">
                    <strong>{{ $trancheClient - $couverture }}</strong> tranche(s) de la grille client n'ont pas
                    d'équivalent ici. Sur ces courses-là, ce livreur reste payé à son
                    <strong>tarif habituel</strong> et la marge n'est pas garantie.
                    <br>
                    Pour remplir toute la grille d'un coup, à un pourcentage du tarif client :
                    <code>php artisan livreur:grille {{ $livreur->id }} --part=60 --apply</code>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <header class="card-header d-flex justify-content-between align-items-center">
            <span>Tranches de facturation</span>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ajoutTranche">
                Ajouter une tranche
            </button>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste"
                              filename="grille-livreur"
                              title="Grille de facturation livreur" />
            <div class="table-responsive">
                <table class="table table-striped align-middle" id="liste">
                    <thead>
                        <tr>
                            <th style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Unité</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Distance (km)</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Le client paie</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">Le livreur touche</th>
                            <th class="text-end" style="background-color: #1c57a3; color: white;">DALAKOUN garde</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tarifs as $t)
                            @php
                                $client = $t->prixClient();
                                $marge  = $t->marge();
                                $taux   = $t->tauxMarge();
                            @endphp
                            <tr @class(['table-danger' => $marge !== null && $marge < 0, 'table-warning' => isset($enConflit[$t->id])])>
                                <td>{{ $t->uniteProduit->libelle ?? '—' }}</td>
                                <td class="text-center">{{ $nb($t->unite_min) }} – {{ $nb($t->unite_max) }}</td>
                                <td class="text-center">{{ $nb($t->distance_min_km) }} – {{ $nb($t->distance_max_km) }}</td>
                                <td class="text-end">
                                    {{ $client !== null ? $fmt($client) : '—' }}
                                    @if ($client === null)
                                        <br><small class="text-muted">aucune tranche client</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">{{ $fmt($t->prix) }}</td>
                                <td class="text-end {{ $marge !== null && $marge < 0 ? 'text-danger fw-bold' : 'text-success' }}">
                                    {{ $marge !== null ? $fmt($marge) : '—' }}
                                    @if ($taux !== null)
                                        <br><small class="text-muted">{{ $nb($taux) }} %</small>
                                    @endif
                                </td>
                                <td class="text-nowrap text-center">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editTranche-{{ $t->id }}" title="Modifier"><i class="material-icons md-edit"></i></button>
                                    <form method="post" action="{{ route('show.grilleLivreur.destroy', [$livreur, $t]) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        {{-- Aucune apostrophe dans le message : delete-confirm.js construit
                                             le texte du SweetAlert par une expression régulière qui
                                             s'arrête à la première, même échappée. --}}
                                        <button type="submit" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Supprimer cette tranche ? Sur ces courses, le livreur repassera sur son tarif habituel.')" title="Supprimer"><i class="material-icons md-delete"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    Aucune tranche pour ce livreur : il est payé à son tarif habituel.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Le tarif d'une tranche couvre <strong>tout le chargement</strong> : il n'est pas multiplié par
                le nombre de rotations, la tranche étant déjà indexée sur la quantité. Un montant supérieur
                au tarif client est refusé — ce ne serait plus une marge, mais une perte.
            </p>
        </div>
    </div>

    {{-- ===== AJOUT ===== --}}
    <div class="modal fade" id="ajoutTranche" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="{{ route('show.grilleLivreur.store', $livreur) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une tranche</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('admin._champsGrilleLivreur', ['t' => null, 'unites' => $unites])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== MODIFICATION ===== --}}
    @foreach ($tarifs as $t)
        <div class="modal fade" id="editTranche-{{ $t->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="post" action="{{ route('show.grilleLivreur.update', [$livreur, $t]) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier la tranche</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin._champsGrilleLivreur', ['t' => $t, 'unites' => $unites])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $corps = $('#liste tbody');
            if ($corps.find('td[colspan]').length) {
                return;
            }

            $('#liste').DataTable({
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                columnDefs: [{ targets: '_all', defaultContent: '-' }],
                pageLength: 25,
                order: [],
            });
        });
    </script>
@endsection
