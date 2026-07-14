@extends('layout.main')
@section('title','Profile apporteur')
@section('contenu')

{{-- @dump($fournisseur) --}}


            <section class="content-main" style="z-index: -2">
                <div class="content-header">
                    <a href="javascript:history.back()"><i class="material-icons md-arrow_back"></i> Retour </a>
                </div>
                <div class="card mb-4">
                    <div class="card-header bg-brand" style="height: 150px"></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl col-lg flex-grow-0" style="flex-basis: 230px">
                                <div class="img-thumbnail shadow w-100 bg-white position-relative text-center" style="height: 190px; width: 200px; margin-top: -120px">
                                    @if($apporteur->piece_recto)
                                        <a href="{{ route('show.apporteurPiece', [$apporteur, 'recto']) }}" target="_blank" title="Voir la pièce recto">
                                            <img src="{{ route('show.apporteurPiece', [$apporteur, 'recto']) }}" class="center-xy img-fluid" alt="Pièce recto" />
                                        </a>
                                    @else
                                        <span class="center-xy text-muted">Non disponible</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-xl col-lg flex-grow-0" style="flex-basis: 230px">
                                <div class="img-thumbnail shadow w-100 bg-white position-relative text-center" style="height: 190px; width: 200px; margin-top: -120px">
                                    @if($apporteur->piece_verso)
                                        <a href="{{ route('show.apporteurPiece', [$apporteur, 'verso']) }}" target="_blank" title="Voir la pièce verso">
                                            <img src="{{ route('show.apporteurPiece', [$apporteur, 'verso']) }}" class="center-xy img-fluid" alt="Pièce verso" />
                                        </a>
                                    @else
                                        <span class="center-xy text-muted">Non disponible</span>
                                    @endif
                                </div>
                            </div>
                            <!--  col.// -->
                            <div class="col-xl col-lg">
                                <h3> {{$apporteur->user->nom_prenoms}} </h3>
                                <p class="mb-1">{{$apporteur->user->email}}</p>
                                @if($apporteur->user->login && $apporteur->user->login !== $apporteur->user->email)
                                    <p class="text-muted mb-1"><small>Identifiant : {{$apporteur->user->login}}</small></p>
                                @endif
                                <span class="badge bg-primary">Code parrain : {{ $apporteur->code }}</span>
                            </div>
                            <!--  col.// -->

                            <!--  col.// -->
                        </div>
                        <!-- card-body.// -->
                        <hr class="my-4" />
                        <div class="row g-4">
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <article class="box">
                                    <p class="mb-0 text-muted">Total filleuls</p>
                                    <h5 class="text-success">{{$apporteur->clients->count()}}</h5>
                                </article>
                            </div>
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <article class="box">
                                    <p class="mb-0 text-muted">Solde commissions</p>
                                    <h5 class="text-success">{{ number_format($apporteur->solde ?? 0, 0, ',', ' ') }} fcfa</h5>
                                </article>
                            </div>
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <article class="box">
                                    <p class="mb-0 text-muted">Taux de commission</p>
                                    <h5 class="text-success">{{ rtrim(rtrim(number_format($apporteur->pourcentage ?? 0, 2, '.', ''), '0'), '.') }} %</h5>
                                </article>
                            </div>
                        </div>

                        <hr class="my-3" />

                        <div class="row g-4">
                            <div class="col-sm-6 col-lg-4">
                                <h6>Contact</h6>
                                <p>{{ $apporteur->user->contact ?: '—' }}</p>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <h6>Numéro de pièce</h6>
                                <p>{{ $apporteur->numero_piece ?: '—' }}</p>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <h6>Lieu de résidence</h6>
                                <p>{{ $apporteur->user->adresse ?: '—' }}</p>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <h6>Zone d'intervention</h6>
                                <p>{{ $apporteur->zone_intervention ?: '—' }}</p>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <h6>Mode de paiement préféré</h6>
                                <p>{{ $apporteur->modePaiement->libelle ?? ($apporteur->mode_paiement_prefere ?: '—') }}</p>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <h6>Coordonnées de paiement</h6>
                                <p>{{ $apporteur->coordonnees_paiement ?: '—' }}</p>
                            </div>
                        </div>
                        <!--  row.// -->
                    </div>
                    <!--  card-body.// -->
                    {{-- ass="card mb-4"> --}}

                    {{-- @if(!$livraisons->isEmpty())
                        <div class="card-body">
                            <h3 class="card-title">Liste des bons d'enlèvement assignés</h3>
                            <div class="row">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover text-center" >
                                            <thead>
                                                <tr>
                                                    <th class="text-center">Code commande</th>
                                                    <th class="text-center">Produit</th>
                                                    <th class="text-center" >Quantité</th>
                                                    <th class="text-center">Fournisseur</th>
                                                    <th class="text-center">Date</th>

                                                </tr>
                                            </thead>
                                            <tbody>

                                                @foreach ($livraisons as $livraison)
                                                    <tr>
                                                        <td>{{ $livraison->enlevement?->code_enleve}}</td>
                                                        <td>{{ $livraison->enlevement?->produit->nom }}</td>
                                                        <td class="text-center"><b> {{ $livraison->enlevement?->qte }}  </b></td>
                                                        <td class="text-center"><b> {{ $livraison->enlevement?->fournisseur->nom_prenoms }}  </b></td>
                                                        <td class="text-center">
                                                            {{ $livraison->date_livraison }}
                                                    </td>
                                                    </tr>
                                                    @endforeach

                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>
                    @else
                        <h1 class="text-center p-3">Aucun bon assigné pour l'instant !!</h1>
                    @endif  --}}

@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>
@endsection
