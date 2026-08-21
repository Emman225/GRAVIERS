@extends('layout.main')
@section('title', 'Grille tarifaire des livraisons')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Grille tarifaire des demandes de livraison</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTarif"
                    onclick="preparerAjout()">
                <i class="material-icons md-add"></i> Ajouter une tranche
            </button>
        </div>
    </div>

    {{-- Clés propres : Flasher capte success/error avant la vue et les rejoue en
         bulle flottante. Un refus de recouvrement est long et doit rester lisible. --}}
    @if (session('succes_grille'))
        <div class="alert alert-success">{{ session('succes_grille') }}</div>
    @endif
    @if (session('erreur_grille'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('erreur_grille') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info">
        <i class="material-icons md-info align-middle"></i>
        Le prix d'un transport est lu dans cette grille : on y cherche la ligne dont l'<strong>unité</strong>,
        la <strong>tranche de quantité</strong> et la <strong>tranche de distance</strong> correspondent à la
        demande. Une ligne <strong>sans ville</strong> vaut partout ; une ligne <strong>avec ville</strong>
        ne vaut que là. Sans ligne correspondante, la demande est <strong>refusée</strong>.
    </div>

    @if ($unitesSansTarif->isNotEmpty())
        <div class="alert alert-warning">
            <i class="material-icons md-warning align-middle"></i>
            <strong>{{ $unitesSansTarif->count() }} unité(s) sans aucun tarif :</strong>
            {{ $unitesSansTarif->pluck('libelle')->implode(', ') }}.
            Toute demande portant l'une d'elles est refusée faute de prix.
        </div>
    @endif

    @if ($distanceMax > 0)
        <div class="alert alert-warning">
            <i class="material-icons md-warning align-middle"></i>
            La grille s'arrête à <strong>{{ rtrim(rtrim(number_format($distanceMax, 2, ',', ' '), '0'), ',') }} km</strong>.
            Au-delà, aucune demande ne peut être chiffrée.
        </div>
    @endif

    @if (!empty($enConflit))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            <strong>{{ count($enConflit) }} tranche(s) en recouvrement.</strong>
            Deux tranches qui se croisent rendent le tarif indéterminé : la recherche retient la première
            venue, sans règle. Les lignes concernées sont signalées ci-dessous — corrigez leurs bornes
            ou supprimez la tranche en trop.
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Unité</th>
                            <th class="text-center">Portée</th>
                            <th class="text-center">Quantité</th>
                            <th class="text-center">Distance (km)</th>
                            <th class="text-end">Tarif</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tarifs as $t)
                            @php
                                // Charge utile du bouton « modifier », préparée ici :
                                // un tableau multiligne écrit directement dans un
                                // attribut onclick ne se compile pas.
                                $donneesTarif = json_encode([
                                    'id'               => $t->id,
                                    'unite_produit_id' => $t->unite_produit_id,
                                    'ville_id'         => $t->ville_id,
                                    'unite_min'        => $t->unite_min,
                                    'unite_max'        => $t->unite_max,
                                    'distance_min_km'  => $t->distance_min_km,
                                    'distance_max_km'  => $t->distance_max_km,
                                    'prix_km'          => $t->prix_km,
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            @endphp
                            <tr @if (isset($enConflit[$t->id])) style="background-color: #ffebee;" @endif>
                                <td class="text-center">{{ $t->uniteProduit?->libelle ?? '—' }}</td>
                                <td class="text-center">
                                    @if ($t->ville_id)
                                        <span class="badge bg-info text-dark">{{ $t->ville?->nom ?? 'Ville #' . $t->ville_id }}</span>
                                    @else
                                        <span class="badge bg-light text-dark">Générique</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $t->unite_min }} — {{ $t->unite_max }}</td>
                                <td class="text-center">{{ $t->distance_min_km }} — {{ $t->distance_max_km }}</td>
                                <td class="text-end"><strong>{{ Help::formatNombre($t->prix_km, true) }}</strong></td>
                                <td class="text-center">
                                    @if (isset($enConflit[$t->id]))
                                        <span class="badge bg-danger" title="Recouvre les tranches #{{ implode(', #', $enConflit[$t->id]) }}">
                                            Recouvre #{{ implode(', #', $enConflit[$t->id]) }}
                                        </span>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTarif"
                                            data-tarif="{{ $donneesTarif }}" onclick="preparerEdition(JSON.parse(this.dataset.tarif))">
                                        <i class="material-icons md-edit"></i>
                                    </button>
                                    <form action="{{ route('show.grilleTarifaire.destroy', $t->id) }}" method="POST" class="d-inline js-delete-form"
                                          data-confirm-title="Supprimer cette tranche"
                                          data-confirm-text="Les demandes correspondant à cette tranche n'auront plus de tarif et seront refusées. Confirmez-vous ?"
                                          data-confirm-button="Oui, supprimer">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="material-icons md-delete"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Aucun tarif défini : toute demande de livraison serait refusée.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MODAL : ajouter / modifier une tranche
         ============================================================ --}}
    <div class="modal fade" id="modalTarif" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="formTarif" action="{{ route('show.grilleTarifaire.store') }}">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="titreModalTarif">Ajouter une tranche</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Unité du produit <span class="text-danger">*</span></label>
                                <select class="form-control" name="unite_produit_id" id="champUnite" required>
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($unites as $u)
                                        <option value="{{ $u->id }}">{{ $u->libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ville</label>
                                <select class="form-control" name="ville_id" id="champVille">
                                    <option value="">Tarif générique (toutes villes)</option>
                                    @foreach ($villes as $v)
                                        <option value="{{ $v->id }}">{{ $v->nom }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Laisser vide pour un tarif valable partout.</small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Quantité min. <span class="text-danger">*</span></label>
                                <input type="number" step="any" min="0" class="form-control" name="unite_min" id="champQteMin" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Quantité max. <span class="text-danger">*</span></label>
                                <input type="number" step="any" min="0" class="form-control" name="unite_max" id="champQteMax" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Distance min. (km) <span class="text-danger">*</span></label>
                                <input type="number" step="any" min="0" class="form-control" name="distance_min_km" id="champKmMin" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Distance max. (km) <span class="text-danger">*</span></label>
                                <input type="number" step="any" min="0" class="form-control" name="distance_max_km" id="champKmMax" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tarif du transport (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="1" class="form-control" name="prix_km" id="champPrix" required>
                                <small class="text-muted">Forfait pour cette tranche, et non un prix au kilomètre.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons md-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        var urlAjout = "{{ route('show.grilleTarifaire.store') }}";
        var gabaritUrlModif = "{{ route('show.grilleTarifaire.update', ['coutLivraison' => '__ID__']) }}";

        function preparerAjout() {
            document.getElementById('titreModalTarif').textContent = 'Ajouter une tranche';
            var f = document.getElementById('formTarif');
            f.action = urlAjout;
            f.reset();
        }

        function preparerEdition(tarif) {
            document.getElementById('titreModalTarif').textContent = 'Modifier la tranche #' + tarif.id;
            var f = document.getElementById('formTarif');
            f.action = gabaritUrlModif.replace('__ID__', tarif.id);
            document.getElementById('champUnite').value  = tarif.unite_produit_id;
            document.getElementById('champVille').value  = tarif.ville_id || '';
            document.getElementById('champQteMin').value = tarif.unite_min;
            document.getElementById('champQteMax').value = tarif.unite_max;
            document.getElementById('champKmMin').value  = tarif.distance_min_km;
            document.getElementById('champKmMax').value  = tarif.distance_max_km;
            document.getElementById('champPrix').value   = tarif.prix_km;
        }

        $(function () {
            // Garde-fou d'initialisation : DataTables lève « Requested unknown
            // parameter » sur un tableau réduit à sa ligne « aucun tarif ».
            var $t = $('#liste');
            if ($t.find('tbody tr').length > 0 && $t.find('tbody tr td[colspan]').length === 0) {
                $t.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [], pageLength: 25
                });
            }
        });
    </script>
@endsection
