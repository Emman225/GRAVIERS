@extends('layout.main')
@section('title', 'Détails de commande')
@section('contenu')
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Détails de la commande {{ $commande->numero }}</h2>
            {{-- <p>Details for Order ID: </p> --}}
        </div>

    </div>
    <div class="card">
        <header class="card-header">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-6 mb-lg-0 mb-15">
                    <span> <i class="material-icons md-calendar_today"></i> <b>{{ $commande->date_commande }}</b> </span>
                    <br />
                    <small class="text-muted">ID commande: {{ $commande->numero }}</small>
                </div>
                {{-- <div class="col-lg-6 col-md-6 ms-auto text-md-end">
                    <select class="form-select d-inline-block mb-lg-0 mr-5 mw-200">
                        <option>Changer l'état</option>
                        <option>En attente de paiement</option>
                        <option>Confirmé</option>
                        <option>Expédié</option>
                        <option>Livré</option>
                    </select>
                    <a class="btn btn-primary" href="#">Enregistrer</a>
                    <a class="btn btn-secondary print ms-2" href="#"><i class="icon material-icons md-print"></i></a>
                </div> --}}
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <div class="row mb-50 mt-20 order-info-wrap">
                <div class="col-md-8">
                    <article class="icontext align-items-start">
                        <span class="icon icon-sm rounded-circle bg-primary-light">
                            <i class="text-primary material-icons md-person"></i>
                        </span>
                        <div class="text">
                            <h6 class="mb-1">Info client</h6>
                            <p class="mb-1">
                                {{ $commande->client->nom }} {{ $commande->client->prenom }} <br />
                                {{ $commande->client->email }} <br />
                                {{ $commande->client->contact1 }} <br>
                                {{ $commande->client->contact2 }}
                            </p>
                            {{-- <a href="#">View profile</a> --}}
                        </div>
                    </article>
                </div>
                <!-- col// -->
                {{-- <div class="col-md-4">
                    <article class="icontext align-items-start">
                        <span class="icon icon-sm rounded-circle bg-primary-light">
                            <i class="text-primary material-icons md-local_shipping"></i>
                        </span>
                        <div class="text">
                            <h6 class="mb-1">Info commande</h6>
                            <p class="mb-1">
                                Livraison: Fargo express <br />
                                Mode de paiement: {{ $lignePaiement?->modePaiement->description }} <br />
                                Statut: new
                            </p>
                        </div>
                    </article>
                </div> --}}

                <!-- col// -->
                <div class="col-md-4">
                    <article class="icontext align-items-start">
                        @if ($commande->est_livrable)
                            <div class="text">
                                <span class="icon icon-sm rounded-circle bg-primary-light">
                                    <i class="text-primary material-icons md-place"></i>
                                </span>
                                    <h6 class="mb-1">Lieu de livraison</h6>
                                    <p class="mb-1">
                                        Pays: {{ ucfirst($commande->adresseLivraison->pays->nom) }}
                                        <br />Ville: {{ ucfirst($commande->adresseLivraison->ville->nom) }}
                                        <br />{{ ucfirst($commande->adresseLivraison->complement_adresse) }} <br />

                                    </p>
                            </div>
                        @endif

                    </article>
                </div>
                <!-- col// -->
            </div>
            <!-- row // -->

            {{-- ============================================================
                 Bon de commande uploadé par le client (entreprise)
                 ============================================================ --}}
            @if ($commande->blClient && $commande->blClient->fichier)
                @php
                    $fichierBl = $commande->blClient->fichier;
                    $extensionBl = strtolower(pathinfo($fichierBl, PATHINFO_EXTENSION));
                    $estPdf = $extensionBl === 'pdf';
                    $estImage = in_array($extensionBl, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                    $urlBl = route('orders.fichierBlClient', ['bl' => $commande->blClient->id, 'mode' => 'inline']);
                    $urlBlDl = route('orders.fichierBlClient', ['bl' => $commande->blClient->id, 'mode' => 'download']);
                @endphp
                <div class="row mb-30">
                    <div class="col-md-12">
                        <div class="card border-info">
                            <div class="card-header bg-info text-dark d-flex justify-content-between align-items-center">
                                <span><i class="material-icons md-attach_file align-middle"></i>
                                    Bon de commande uploadé
                                    @if ($commande->blClient->numero)
                                        — N° {{ $commande->blClient->numero }}
                                    @endif
                                </span>
                                <div>
                                    <a href="{{ $urlBl }}" target="_blank" class="btn btn-sm btn-light">
                                        <i class="material-icons md-visibility align-middle"></i> Consulter
                                    </a>
                                    <a href="{{ $urlBlDl }}" class="btn btn-sm btn-light">
                                        <i class="material-icons md-cloud_download align-middle"></i> Télécharger
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                @if ($estPdf)
                                    <embed src="{{ $urlBl }}" type="application/pdf"
                                           width="100%" height="600px"
                                           style="border: 1px solid #ddd;" />
                                @elseif ($estImage)
                                    <div class="text-center">
                                        <img src="{{ $urlBl }}" alt="Bon de commande"
                                             style="max-width: 100%; max-height: 600px; border: 1px solid #ddd;">
                                    </div>
                                @else
                                    <p class="text-muted">
                                        Format <strong>{{ $extensionBl }}</strong> non prévisualisable —
                                        utilisez les boutons « Consulter » ou « Télécharger ».
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-12">
                    <div class="table-responsive">
                        <table disabled class="table">
                            <thead>
                                <tr>
                                    <th width="30%">Produit</th>
                                    <th width="13%">Prix unitaire</th>
                                    <th width="10%">Qté</th>
                                    <th width="10%">Qté livrée</th>
                                    <th width="12%">Statut livraison</th>
                                    <th width="13%">Reste à traiter</th>
                                    <th width="12%" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- @dd($details->produit) --}}
                                @foreach ($commande->produits as $produit)
                                    @php
                                        $qte = (float) $produit->pivot->qte;
                                        $qteLivree = (float) ($produit->pivot->qte_livree ?? 0);
                                        $qteRestante = max(0, $qte - $qteLivree);
                                        if ($qteLivree <= 0) {
                                            $statut = 'NON_LIVREE';
                                            $badge = 'secondary';
                                            $libelleStatut = 'Non livrée';
                                        } elseif ($qteLivree >= $qte) {
                                            $statut = 'TOTALE';
                                            $badge = 'success';
                                            $libelleStatut = 'Livrée totale';
                                        } else {
                                            $statut = 'PARTIELLE';
                                            $badge = 'warning';
                                            $libelleStatut = 'Livraison partielle';
                                        }
                                        $montantRestant = $qteRestante * (float) $produit->pivot->prix;
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="itemside">
                                                <div class="info">{{ $produit->nom }}</div>
                                            </a>
                                        </td>
                                        <td>{{ Help::formatNombre($produit->pivot->prix, true) }}</td>
                                        <td>{{ Help::formatNombre($qte, false) }}</td>
                                        <td>{{ Help::formatNombre($qteLivree, false) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $badge }}">{{ $libelleStatut }}</span>
                                        </td>
                                        <td>
                                            @if ($qteRestante > 0)
                                                <strong>{{ Help::formatNombre($qteRestante, false) }}</strong>
                                                <br><small class="text-danger">
                                                    {{ Help::formatNombre($montantRestant, true) }}
                                                </small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            {{ Help::formatNombre($produit->pivot->prix * $produit->pivot->qte, true) }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    @php
                                        $total = $commande->montant_total + $commande->cout_livraison_client + $commande->TvaCommande->montant - $commande->remise;
                                    @endphp
                                    <td colspan="7">
                                        <article class="float-end">
                                            <dl class="dlist">
                                                <dt>Sous-total:</dt>
                                                <dd>{{Help::formatNombre($commande->montant_total, true)}}</dd>
                                            </dl>
                                            <dl class="dlist">
                                                <dt>Cout de livraison:</dt>
                                                <dd>{{Help::formatNombre($commande->cout_livraison_client, true)}}</dd>
                                            </dl>
                                            <dl class="dlist">
                                                <dt>TVA:</dt>
                                                <dd>{{Help::formatNombre($commande->TvaCommande->montant, true)}}</dd>
                                            </dl>
                                            @if($commande->remise)
                                                <dl class="dlist">
                                                    <dt>Remise:</dt>
                                                    <dd>{{Help::formatNombre($commande->remise, true)}}</dd>
                                                </dl>
                                            @endif
                                            <dl class="dlist">
                                                <dt>Total:</dt>
                                                <dd><b class="h5">{{ Help::formatNombre($total, true) }} </b></dd>
                                            </dl>
                                            <dl class="dlist">

                                                <dd>

                                                </dd>
                                            </dl>
                                        </article>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive// -->
                </div>
            </div>
            <!-- card-body end// -->
        </div>
    </div>
    <!-- card end// -->
@endsection
