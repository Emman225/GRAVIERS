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
                                {{ $commande->client?->display_name }} <br />
                                {{ $commande->client?->email }} <br />
                                {{ $commande->client?->contact1 }} <br>
                                {{ $commande->client?->contact2 }}
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
                                Mode de paiement: {{ $lignePaiement?->modePaiement?->description }} <br />
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
                                        Pays: {{ ucfirst($commande->adresseLivraison->pays?->nom) }}
                                        <br />Ville: {{ ucfirst($commande->adresseLivraison->ville?->nom) }}
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
                            {{-- L'aperçu du fichier (PDF ou image) a été retiré le 08/09/2026 :
                                 il occupait toute la page. La barre suffit : « Consulter »
                                 l'ouvre dans un onglet, « Télécharger » l'enregistre. --}}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-12">
                    {{-- Les trois exports, comme sur les listes (08/09/2026). --}}
                    <x-export-buttons table-id="tableDetailsCommande"
                                      filename="commande-{{ $commande->numero }}"
                                      title="Détail de la commande {{ $commande->numero }}" />
                    <div class="table-responsive">
                        <table class="table" id="tableDetailsCommande">
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

                                        // Quantité livrée : la colonne qte_livree n'était
                                        // alimentée que par l'application mobile du livreur.
                                        // Une livraison validée depuis le site laissait la
                                        // ligne à 0 et le statut affichait « Non livrée »
                                        // alors que la marchandise était bien partie.
                                        // On retient donc la valeur la plus avancée entre la
                                        // colonne et la somme des livraisons marquées LIVREE.
                                        // La quantité d'une livraison reste celle qui a été
                                        // DEMANDÉE : quand le fournisseur sert moins, seul
                                        // l'enlèvement porte la quantité servie
                                        // (SellerController n'écrit que `qte_servi`). On
                                        // additionne donc, livraison par livraison, ce qui a
                                        // réellement été servi — même règle que le paiement
                                        // du fournisseur et que la facture.
                                        $qteLivreeLivraisons = (float) \App\Models\Livraison::with('enlevement')
                                            ->where('detail_commande_id', $produit->pivot->id)
                                            ->where('etat_livraison', \Help::$LIVRAISON_LIVREE)
                                            ->get()
                                            ->sum(fn ($uneLivraison) => $uneLivraison->enlevement
                                                ? $uneLivraison->enlevement->quantiteAPayer()
                                                : (float) $uneLivraison->qte);

                                        // Entre les deux sources, la somme des livraisons est
                                        // la seule qui connaisse la quantité servie : la
                                        // colonne `qte_livree` est incrémentée avec la
                                        // quantité DEMANDÉE (LivreurController, côté site
                                        // comme côté mobile). La prendre par un max()
                                        // annulerait la correction ci-dessus. Elle ne sert
                                        // donc plus que de repli, pour le cas qu'elle
                                        // couvrait déjà : aucune livraison encore marquée
                                        // LIVREE alors que la ligne, elle, a avancé.
                                        $qteLivree = min($qte, $qteLivreeLivraisons > 0
                                            ? $qteLivreeLivraisons
                                            : (float) ($produit->pivot->qte_livree ?? 0));
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

                                {{-- Codes à communiquer au client : numéro de livraison (que le
                                     client donne au livreur) et code d'enlèvement (retrait chez le
                                     fournisseur). Ils n'apparaissaient NULLE PART dans le
                                     back-office : quand l'e-mail au client échouait, personne ne
                                     pouvait les retrouver pour les lui redonner. --}}
                                @php
                                    // Une livraison est rattachée à une LIGNE de commande
                                    // (detail_commande_id) : la relation Commande::livraisons()
                                    // vise une colonne commande_id qui n'existe pas dans la table.
                                    $livraisonsCommande = \App\Models\Livraison::whereIn(
                                            'detail_commande_id',
                                            $commande->detailCommande->pluck('id')
                                        )
                                        ->orderBy('id')
                                        ->get();
                                @endphp
                                @php
                                    // UN CODE REFUSÉ N'EST PLUS UN CODE À DONNER.
                                    //
                                    // Toutes les courses étaient listées côte à côte, refus
                                    // compris, sous le titre « Codes à communiquer au client ».
                                    // Le client se retrouvait avec DEUX codes sans savoir lequel
                                    // valait — et le refusé s'affichait « EN ATTENTE », puisque
                                    // seul `accepte` change au refus, jamais `etat_livraison`.
                                    // Le libellé disait donc l'exact contraire de la réalité.
                                    $codesValables = $livraisonsCommande->where('accepte', '!=', \App\Models\Livraison::REFUSEE);
                                    $coursesRefusees = $livraisonsCommande->where('accepte', \App\Models\Livraison::REFUSEE);
                                @endphp
                                @if ($livraisonsCommande->isNotEmpty())
                                    <tr>
                                        <td colspan="7" style="background:#f8f9fa;">
                                            {{-- Les NUMÉROS de livraison et les BONS d'enlèvement ne sont
                                                 plus affichés ici (08/09/2026) : ils ne servent qu'au
                                                 client, au livreur et au fournisseur. On garde, produit
                                                 par produit, qui a traité et servi chaque course. --}}
                                            @if ($codesValables->isNotEmpty())
                                                <strong>Livraisons de la commande</strong>
                                                <div class="mt-2">
                                                    @foreach ($codesValables as $uneLivraison)
                                                        @php
                                                            $bonServi  = $uneLivraison->enlevement;
                                                            $traitePar = $bonServi?->gestionnaire?->nom_prenoms ?: null;
                                                            $servePar  = $bonServi?->fournisseur?->user?->nom_prenoms ?: null;
                                                            $produitLivre = $uneLivraison->detailCommande?->produit?->nom;
                                                            $qteLivraison = $uneLivraison->qte ?? null;
                                                        @endphp
                                                        <div class="mb-1">
                                                            <span class="badge bg-info">Course</span>
                                                            <strong>{{ $produitLivre ?: 'Produit' }}</strong>
                                                            @if ($qteLivraison)
                                                                — {{ rtrim(rtrim(number_format((float) $qteLivraison, 2, ',', ' '), '0'), ',') }}
                                                                {{ $uneLivraison->detailCommande?->produit?->unite ?? '' }}
                                                            @endif
                                                            <small class="text-muted">&nbsp;— {{ $uneLivraison->etatLisible() }}</small>
                                                            @if ($traitePar)
                                                                <div>
                                                                    <small class="text-muted">
                                                                        Traité par <strong>{{ $traitePar }}</strong>
                                                                    </small>
                                                                </div>
                                                            @endif
                                                            @if ($bonServi?->fournisseur_validation)
                                                                <div>
                                                                    <small class="text-success">
                                                                        Enlèvement servi le
                                                                        {{ \Carbon\Carbon::parse($bonServi->fournisseur_validation)->format('d/m/Y à H:i:s') }}
                                                                        @if ($servePar)
                                                                            par le fournisseur <strong>{{ $servePar }}</strong>
                                                                        @endif
                                                                        @if ($bonServi->qte_servi !== null && (float) $bonServi->qte_servi < (float) $bonServi->qte)
                                                                            — <span class="text-danger">{{ rtrim(rtrim(number_format((float) $bonServi->qte_servi, 2, ',', ' '), '0'), ',') }}
                                                                            sur {{ rtrim(rtrim(number_format((float) $bonServi->qte, 2, ',', ' '), '0'), ',') }}</span>
                                                                        @endif
                                                                    </small>
                                                                </div>
                                                            @elseif ($bonServi)
                                                                <div>
                                                                    <small class="text-warning">En attente du fournisseur</small>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <strong>Aucune course active</strong>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        Toutes les courses affectées ont été refusées :
                                                        réaffectez cette ligne pour qu'une nouvelle course soit créée.
                                                    </small>
                                                </div>
                                            @endif

                                            @if ($coursesRefusees->isNotEmpty())
                                                <div class="mt-3 pt-2" style="border-top:1px dashed #dee2e6;">
                                                    <small class="text-muted">
                                                        <strong>{{ $coursesRefusees->count() }} course(s) refusée(s) par le livreur</strong>
                                                        — la ligne concernée a été, ou doit être, réaffectée.
                                                    </small>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    @php
                                        $total = $commande->montantAPayer();
                                    @endphp
                                    <td colspan="7">
                                        <article class="float-end">
                                            <dl class="dlist">
                                                <dt>Sous-total:</dt>
                                                <dd>{{Help::formatNombre($commande->montantHT(), true)}}</dd>
                                            </dl>
                                            <dl class="dlist">
                                                <dt>Cout de livraison:</dt>
                                                <dd>{{Help::formatNombre($commande->cout_livraison_client, true)}}</dd>
                                            </dl>
                                            <dl class="dlist">
                                                <dt>TVA:</dt>
                                                <dd>{{Help::formatNombre($commande->TvaCommande?->montant ?? 0, true)}}</dd>
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
