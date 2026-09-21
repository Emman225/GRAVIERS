@php use Illuminate\Support\Carbon; @endphp
@extends('layout.main')
@section('title','Locations traitées')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title card-title">Locations traitées</h2>
    </div>

    @if(session('success'))
        <div class="alert alert-success text-center">{{ session('success') }}</div>
    @endif

    {{-- LES FILTRES.

         Aucune borne par défaut : l'écran a toujours montré toutes les
         locations traitées, et en restreindre l'affichage sans qu'on l'ait
         demandé ferait croire à des locations disparues. Chaque critère ne
         s'applique donc que s'il est rempli.

         Formulaire en GET — pas de @csrf, le jeton se retrouverait dans
         l'URL — pour que la recherche puisse se mettre en favori. --}}
    @php
        $filtreActif = ($etat ?? '') !== '' || ($paiement ?? '') !== ''
            || ($client ?? '') !== '' || !empty($du) || !empty($au);
    @endphp

    <div class="card mb-4">
        <form method="GET" action="{{ route('show.locationsTraitees') }}"
              class="row gx-3 gy-2 p-3 align-items-end">
            <div class="col-md-4 col-lg-2">
                <label for="etat" class="form-label mb-1 small text-muted">État</label>
                <select class="form-control" name="etat" id="etat">
                    <option value="">Tous</option>
                    <option value="EN COURS" @selected(($etat ?? '') === 'EN COURS')>En cours</option>
                    <option value="TERMINE" @selected(($etat ?? '') === 'TERMINE')>Terminé</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label for="paiement" class="form-label mb-1 small text-muted">Paiement</label>
                <select class="form-control" name="paiement" id="paiement">
                    <option value="">Tous</option>
                    <option value="SOLDE" @selected(($paiement ?? '') === 'SOLDE')>Soldé</option>
                    <option value="PARTIEL" @selected(($paiement ?? '') === 'PARTIEL')>En cours</option>
                    <option value="AUCUN" @selected(($paiement ?? '') === 'AUCUN')>Aucun</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-3">
                <label for="client" class="form-label mb-1 small text-muted">Client</label>
                <input type="text" class="form-control" name="client" id="client"
                       value="{{ $client ?? '' }}" placeholder="Nom, même partiel">
            </div>
            <div class="col-md-3 col-lg-2">
                <label for="du" class="form-label mb-1 small text-muted">Retour du</label>
                <input type="date" class="form-control" name="du" id="du" value="{{ $du ?? '' }}">
            </div>
            <div class="col-md-3 col-lg-2">
                <label for="au" class="form-label mb-1 small text-muted">au</label>
                <input type="date" class="form-control" name="au" id="au" value="{{ $au ?? '' }}">
            </div>
            <div class="col-md-6 col-lg-1">
                <button type="submit" class="btn btn-primary w-100" title="Appliquer les filtres">
                    <i class="material-icons md-search"></i>
                </button>
            </div>
            <div class="col-12">
                <span class="text-muted small">
                    @if ($filtreActif)
                        {{ count($locations) }} location{{ count($locations) > 1 ? 's' : '' }} trouvée{{ count($locations) > 1 ? 's' : '' }}
                        <a href="{{ route('show.locationsTraitees') }}" class="ms-2">Retirer les filtres</a>
                    @else
                        Toutes les locations traitées — {{ count($locations) }}
                    @endif
                </span>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <x-export-buttons table-id="listeLocTraitees"
                                      filename="locations-traitees"
                                      title="Locations traitées" />
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="listeLocTraitees">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">N°</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Client</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Livreur</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Montant</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Caution</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">État</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Date retour</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Paiement</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Livraison / enlèvement</th>
                                    <th class="text-center" style="background-color:#1c57a3;color:#fff;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($locations as $location)
                                    @php $etat = $location->etatLibelle(); @endphp
                                    <tr>
                                        <td class="text-center">{{ $location->numero }}</td>
                                        <td class="text-center"><b>{{ $location->client?->display_name }}</b></td>
                                        <td class="text-center">{{ $location->livreur?->user?->nom_prenoms ?? '-' }}</td>
                                        <td class="text-center">{{ number_format($location->montant_total, 0, '', ' ') }} fcfa</td>
                                        <td class="text-center">
                                            {{ number_format($location->caution ?? 0, 0, '', ' ') }} fcfa
                                            @if (($location->caution_retenue ?? 0) > 0)
                                                <br><small class="text-danger">retenue : {{ number_format($location->caution_retenue, 0, '', ' ') }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill {{ $etat === 'EN COURS' ? 'bg-info text-dark' : 'bg-success' }}">{{ $etat }}</span>
                                        </td>
                                        <td class="text-center">{{ $location->date_retour ? Carbon::parse($location->date_retour)->format('d-m-Y') : '-' }}</td>
                                        {{-- CETTE COLONNE LIT L ARGENT, PAS UN DRAPEAU.

                                             Elle lisait `location.statut`, une valeur derivee rangee
                                             une seconde fois : une location encaissee avant que le
                                             guichet ne pose ce drapeau restait marquee « Aucun » pour
                                             toujours, alors que l argent etait en caisse. Voir
                                             Location::etatPaiement(). --}}
                                        <td class="text-center">
                                            @php $etatPaiement = $location->etatPaiement(); @endphp
                                            @if ($etatPaiement === 'SOLDE')
                                                <span class="badge rounded-pill alert-success text-success">Soldé</span>
                                            @elseif ($etatPaiement === 'PARTIEL')
                                                <span class="badge rounded-pill alert-success text-warning">En cours</span>
                                                <br><small class="text-muted">{{ number_format($location->montantPayeComptant(), 0, '', ' ') }} / {{ number_format($location->montantAPayer(), 0, '', ' ') }}</small>
                                            @else
                                                <span class="badge rounded-pill alert-success text-danger">Aucun</span>
                                            @endif
                                        </td>
                                        {{-- LES CODES DE VALIDATION, RETROUVABLES.
                                             Ils n'apparaissaient nulle part : quand le courriel au
                                             client échouait — et il échouait en silence — plus
                                             personne ne pouvait les lui redonner, et la location
                                             ne pouvait plus être validée par le livreur.

                                             Une course REFUSÉE porte un code sans effet : la
                                             donner au client, c'est le bloquer. Elle est donc
                                             séparée et barrée. Au refus, seule `accepte` change —
                                             `etat_livraison` reste sur « EN ATTENTE » et mentirait. --}}
                                        <td class="text-center">
                                            @php
                                                $coursesLocation = \App\Models\Livraison::whereIn(
                                                        'detail_commande_id',
                                                        $location->detailLocation->pluck('id')
                                                    )
                                                    ->where('provenance', 'LOCATION')
                                                    ->orderBy('id')
                                                    ->get();
                                                $codesValables = $coursesLocation->reject->estRefusee();
                                                $coursesRefusees = $coursesLocation->filter->estRefusee();
                                            @endphp

                                            @forelse ($codesValables as $course)
                                                @php
                                                    // LE BON D'ENLÈVEMENT DE CETTE COURSE.
                                                    // Une location n'en produisait aucun : le livreur
                                                    // se présentait chez le fournisseur sans code,
                                                    // alors que la vente lui en donne un. Les deux
                                                    // codes se lisent maintenant ici, comme pour une
                                                    // vente — celui du client pour clore la course,
                                                    // celui du fournisseur pour retirer le matériel.
                                                    $bon = \App\Models\Enlevement::where('livraison_id', $course->id)
                                                        ->whereNull('deleted_at')
                                                        ->first();
                                                @endphp
                                                <div class="mb-1">
                                                    {{-- RETRAIT SUR PLACE : PAS DE CODE DE VALIDATION.
                                                         Le code « client » sert à clore une livraison
                                                         auprès du livreur. Quand le client vient
                                                         chercher lui-même le matériel, aucune
                                                         livraison n'a lieu : l'afficher ferait
                                                         chercher au gestionnaire un usage qui
                                                         n'existe pas. Seul le code d'enlèvement
                                                         compte, et c'est le CLIENT qui le reçoit. --}}
                                                    @if ($course->livre_par == 2)
                                                        <div>
                                                            <span class="badge bg-info">Retrait sur place</span>
                                                            <small class="text-muted">— le client retire chez le fournisseur</small>
                                                        </div>
                                                    @else
                                                        {{-- PLUS DE CODES ICI (10/09/2026). Le code de livraison est
                                                             au client (Mon compte, application), le bon d'enlèvement
                                                             au livreur (son application) ou au client en retrait :
                                                             chacun le lit chez lui. Le gestionnaire garde l'ÉTAT. --}}
                                                        <div>
                                                            <small class="text-muted">Course :</small>
                                                            <strong>{{ $course->etatLisible() }}</strong>
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <small class="text-muted">Enlèvement :</small>
                                                        @if ($bon && $bon->code_enleve)
                                                            <strong>{{ optional(optional($bon->fournisseur)->user)->nom_prenoms ?? 'fournisseur inconnu' }}</strong>
                                                            {{-- LE CODE SEUL NE DIT PAS SI LE MATÉRIEL EST SORTI.
                                                                 Tant que le fournisseur n'a pas validé, rien n'a été
                                                                 remis : le gestionnaire donnait le code sans savoir où
                                                                 en était l'enlèvement, et devait ouvrir une autre page
                                                                 pour le vérifier.

                                                                 Une fois validé, ce qui compte n'est plus le fait mais
                                                                 la QUANTITÉ SERVIE : un enlèvement partiel laisse du
                                                                 matériel à retirer. --}}
                                                            <br>
                                                            @if ($bon->estValideParFournisseur())
                                                                <span class="badge rounded-pill bg-success">
                                                                    {{ $bon->libelleValidationFournisseur() }}
                                                                </span>
                                                            @else
                                                                <span class="badge rounded-pill bg-warning text-dark">
                                                                    {{ $bon->libelleValidationFournisseur() }}
                                                                </span>
                                                            @endif
                                                        @else
                                                            <small class="text-muted">
                                                                aucun bon — location validée avant l'ajout des bons d'enlèvement
                                                            </small>
                                                        @endif
                                                    </div>
                                                </div>
                                            @empty
                                                <small class="text-muted">
                                                    {{ $coursesRefusees->isNotEmpty()
                                                        ? 'Toutes les courses ont été refusées : réaffectez.'
                                                        : '—' }}
                                                </small>
                                            @endforelse

                                            @if ($coursesRefusees->isNotEmpty())
                                                <div class="mt-1" style="border-top:1px dashed #dee2e6;">
                                                    <small class="text-muted">{{ $coursesRefusees->count() }} course(s) refusée(s) par le livreur — à réaffecter.</small>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-end">
                                            @if ($etat === 'EN COURS')
                                                <a href="{{ route('show.retourLocationPage', $location) }}" class="btn btn-sm btn-success rounded font-sm" title="Retour matériel"><i class="material-icons md-assignment_return"></i></a>
                                            @endif
                                            {{-- Voir le commentaire de listeLocation : le règlement passe
                                                 désormais par le guichet des encaissements. --}}
                                            @if (!$location->estSoldee())
                                                <a href="{{ route('show.encaissements.locations', ['location' => $location->numero]) }}"
                                                   class="btn btn-sm btn-warning rounded font-sm" title="Encaisser le reste"><i class="material-icons md-payments"></i></a>
                                            @endif
                                            {{-- Facture FNE : générer (une fois, paiement soldé ou client à terme exigé) puis consulter. --}}
                                            @if ($location->factureFne)
                                                <a href="{{ route('orders.factureLocation', ['facture' => $location->factureFne->id, 'action' => 'voir']) }}" target="_blank" class="btn btn-sm btn-info rounded font-sm" title="Voir facture"><i class="material-icons md-visibility"></i></a>
                                            @elseif ($location->estSoldee() || $location->client?->client_a_terme == 1)
                                                <form action="{{ route('orders.genererFactureLocation', $location) }}" method="post" style="display:inline-block">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-secondary rounded font-sm"
                                                        onclick="return confirm('Générer la facture FNE de cette location ?');" title="Générer facture"><i class="material-icons md-receipt_long"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
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
        $(function() {
            $('#listeLocTraitees').DataTable({
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                order: [],
            });
        });
    </script>
@endsection
