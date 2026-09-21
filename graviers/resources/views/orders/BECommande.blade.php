@php
    use Illuminate\Support\Carbon;
    $qteAEnlever = 0;
@endphp

@extends('layout.main')
@section('title', 'Enlevements')
@section('contenu')
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Enlevement de la commande {{ $commande->numero }} </h2>
            {{-- <p>Details for Order ID: </p> --}}
        </div>
        {{-- Word et PDF viennent du SERVEUR : ils reprennent la mise en page du
             bon, en-tête et logo compris. Excel, lui, reconstitue les lignes
             depuis l'écran — c'est un tableau de travail, pas un document. --}}
        <div>
            <x-export-buttons container="bonEnlevementLignes"
                filename="bon-enlevement-{{ $commande->numero }}"
                title="Bon d'enlèvement — commande {{ $commande->numero }}"
                :word-url="route('orders.BECommande.word', $commande->numero)"
                :pdf-url="route('orders.BECommande.pdf', $commande->numero)" />
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
                                {{ $commande->le_client }}<br />
                                {{ $commande->client?->user?->email }} <br />
                                {{ $commande->contact1 }} <br>
                            </p>
                        </div>
                    </article>
                </div>
                <!-- col// -->
                @if ($commande->est_livrable)
                    <div class="col-md-4">
                        <article class="icontext align-items-start">
                            <span class="icon icon-sm rounded-circle bg-primary-light">
                                <i class="text-primary material-icons md-place"></i>
                            </span>
                            <div class="text">
                                <h6 class="mb-1">Lieu de livraison</h6>
                                <p class="mb-1">
                                    Pays: {{ ucfirst($commande->lePays) }}
                                    <br />Ville: {{ ucfirst($commande->laVille) }} <br />
                                    <br />{{ ucfirst($commande->adresse) }} <br />

                                </p>
                                {{-- <a href="#">View profile</a> --}}
                            </div>
                        </article>
                    </div>
                @endif
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
                            {{-- L'aperçu du fichier a été retiré le 08/09/2026 : la barre
                                 suffit, « Consulter » l'ouvre, « Télécharger » l'enregistre. --}}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-12">
                    {{-- Les trois exports du tableau des produits (08/09/2026). --}}
                    <x-export-buttons table-id="tableProduitsBE"
                                      filename="produits-commande-{{ $commande->numero }}"
                                      title="Produits de la commande {{ $commande->numero }}" />
                    <div class="table-responsive">
                        <table class="table" id="tableProduitsBE">
                            <thead>
                                <tr>
                                    <th width="40%">Produit</th>
                                    <th width="20%">Prix unitaire</th>
                                    <th width="20%">Quantité (en tonne)</th>
                                    <th width="20%" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- @dd($details->produit) --}}
                                @foreach ($commande->produits as $produit)
                                    <tr>
                                        <td>
                                            <a class="itemside">
                                                <div class="info">{{ $produit->nom }}</div>
                                            </a>
                                        </td>
                                        <td>{{ number_format($produit->pivot->prix, '0', '', ' ') }} fcfa</td>
                                        <td> {{ $produit->pivot->qte }} </td>
                                        <td class="text-end">
                                            {{ number_format($produit->pivot->prix * $produit->pivot->qte, '0', '', ' ') }}
                                            fcfa <br>
                                            {{-- <small class="text-success"> {{ $produit->pivot->statut }} </small> --}}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    @php
                                        // Total net depuis les LIGNES (montant_total : HT côté web
                                        // mais net final côté mobile -> double comptage sinon).
                                        $total = $commande->montantAPayer();
                                    @endphp
                                    <td colspan="4">
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
                                                <dd><b class="h5">
                                                        {{ Help::formatNombre( $total, true) }}
                                                    </b>
                                                </dd>
                                            </dl>
                                            <dl class="dlist">
                                                <dt class="text-muted">Statut:</dt>
                                                <dd>
                                                    {{-- Le fcfa n'a pas de décimales : un restant < 1 (résidu
                                                         d'arrondi TVA, ex. 0,2) = commande soldée. --}}
                                                    @if ($restant >= $montantAPayer && ($montantEnAttente ?? 0) > 0)
                                                        {{-- Le client a versé, le caissier a encaissé : dire
                                                             « aucun paiement » serait faux pour les deux. Le
                                                             règlement attend seulement sa seconde validation. --}}
                                                        <span class="badge bg-info text-dark">
                                                            Paiement saisi, en attente de validation
                                                        </span>
                                                        <div class="small text-muted mt-1">
                                                            {{ Help::formatNombre($montantEnAttente, true) }}
                                                            à valider par un second administrateur
                                                        </div>
                                                    @elseif ($restant >= $montantAPayer)
                                                        <span class="badge bg-danger">
                                                            Aucun paiement effectué
                                                        </span>
                                                    @elseif ($restant >= 1)
                                                        <span class="badge bg-warning text-dark">
                                                            Paiement en cours...
                                                        </span>
                                                    @else
                                                        <span class="badge bg-success">
                                                            Paiement soldé
                                                        </span>
                                                    @endif
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
                <form action="{{ route('orders.genererFacture', $commande) }}" method="post">
                    @csrf

                    @php
                        $genFact = false;
                    @endphp
                    @error('enlevements')
                        <span class="alert alert-danger text-center mb-20"> {{$message}} </span>
                    @enderror
                    {{-- Conteneur repéré par l'export Excel : il rassemble un tableau
                         par produit, chacun précédé de son titre. --}}
                    <div id="bonEnlevementLignes">
                    @foreach ($details as $detail)
                        <div class="col-lg-12">
                            <div class="table-responsive mt-20">
                                <h3>{{ $detail->nom }}</h3>
                                <table class="table table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            {{-- Colonne d'action : elle porte la case de selection multiple
                                                 ET le bouton de facturation a l'unite. Sans largeur, le bouton
                                                 debordait sur la colonne voisine. --}}
                                            <th style="background-color: #1c57a3; color: white; border-top-left-radius:5px; min-width:135px;"
                                                width="135">Facturer</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Qte Restante <i>(en tonne)</i>
                                            </th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Prix
                                                Unitaire</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Montant</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Qté des
                                                enlèvements (en tonne)</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Qté
                                                restantes à enlever (en tonne)</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Prix
                                                unitaire</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Montant
                                                Enlèvement</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Montant
                                                Restant</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Traité par
                                            </th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Date</th>
                                            <th style="background-color: #1c57a3; color: white;" width="">Statut</th>
                                            <th style="background-color: #1c57a3; color: white; border-top-right-radius:5px"
                                                width="">N° Facture</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $qteRestante = $detail->qte;
                                        @endphp
                                        @foreach ($detail->livs as $livraison)
                                            @php
                                                $bon = $livraison->bon ?? null;
                                                // La règle d'éligibilité vient du modèle : retrait sur place validé
                                                // par le fournisseur, ou livraison clôturée. Cf. Enlevement::estFacturable.
                                                $facturable = $bon?->estFacturable() ?? false;
                                                $partiel    = $bon?->estServiPartiellement() ?? false;
                                                // Quantité réellement remise par le fournisseur : c'est elle qui sera
                                                // facturée. Tant qu'il n'a pas validé, on retient la quantité demandée.
                                                $qteEnlevee = $bon ? $bon->quantiteAPayer() : (float) $livraison->qte_enleve;
                                                if ($facturable) {
                                                    $genFact = true;
                                                }
                                            @endphp
                                            <tr style="" class="text-danger">
                                                <td class="text-center align-middle" style="min-width:135px;">
                                                    {{-- Empile case, bouton et badge : sans conteneur, la case
                                                         flottante et le bouton se chevauchaient. --}}
                                                    <div class="d-flex flex-column align-items-center" style="gap:8px;">
                                                    @if ($facturable)
                                                        {{-- Case centrée et visible (l'ancien style Bootstrap sans
                                                             conteneur .form-check la rendait minuscule/décalée) --}}
                                                        <input type="checkbox" name="enlevements[]" class="form-check-input"
                                                            style="width:20px;height:20px;margin:0;cursor:pointer;border:2px solid #1c57a3;position:static;flex:none;"
                                                            title="Sélectionner cet enlèvement pour générer la facture"
                                                            value="{{ $livraison->id_enlevement }}" id="">

                                                        {{-- Facturer CE bon seul, sans attendre les autres lignes.
                                                             Le bouton est dans le tableau, donc dans le formulaire de
                                                             sélection : il ne peut pas être déplacé auprès du sien.
                                                             Il désigne donc son formulaire par identifiant, via une
                                                             FONCTION NOMMÉE — un appel à confirm() écrit directement
                                                             dans l'attribut serait capté par delete-confirm.js, qui
                                                             retire l'onclick et rendait le bouton inerte. --}}
                                                        <button type="button"
                                                            class="btn btn-sm {{ $partiel ? 'btn-warning' : 'btn-outline-primary' }} text-nowrap"
                                                            title="Générer la facture DGI de ce seul bon d'enlèvement"
                                                            onclick="facturerCeBon('factureBon{{ $livraison->id_enlevement }}')">
                                                            Facture DGI
                                                        </button>
                                                    @endif

                                                    @if ($partiel)
                                                        <span class="badge bg-warning text-dark"
                                                            title="Le fournisseur a servi moins que la quantité demandée.">
                                                            Partiel
                                                        </span>
                                                    @endif
                                                    </div>
                                                </td>

                                                <td> {{ $qteRestante }}</td> {{-- qte restante --}}

                                                <td> {{ number_format(($detail->prix ?? $detail->prix_moyen), '0', '', ' ') }}
                                                    fcfa </td> {{-- prix unitaire --}}

                                                <td> {{ number_format(($detail->prix ?? $detail->prix_moyen) * $qteRestante, '0', '', ' ') }}
                                                    fcfa</td> {{-- montant --}}

                                                {{-- <td> {{$livraison->enlevement?->code_enleve}} </td> numéro --}}

                                                <td>
                                                    {{ rtrim(rtrim(number_format($qteEnlevee, 2, ',', ' '), '0'), ',') }}
                                                    @if ($partiel)
                                                        <small class="text-muted d-block">
                                                            demandé : {{ rtrim(rtrim(number_format((float) $livraison->qte_enleve, 2, ',', ' '), '0'), ',') }}
                                                        </small>
                                                    @endif
                                                    @php $qteAEnlever += $qteEnlevee @endphp
                                                </td>
                                                {{-- qte des enlevement : quantité SERVIE, celle qui sera facturée --}}

                                                <td> {{ rtrim(rtrim(number_format($qteRestante - $qteEnlevee, 2, ',', ' '), '0'), ',') }} </td>
                                                {{-- qte restante à enlever --}}

                                                <td>{{ number_format(($detail->prix ?? $detail->prix_moyen), '0', '', ' ') }} fcfa
                                                </td>
                                                {{-- prix unitaire --}}

                                                <td> {{ number_format($qteEnlevee * ($detail->prix ?? $detail->prix_moyen), '0', '', ' ') }}
                                                    fcfa</td> {{-- montant enlevement --}}

                                                <td> {{ number_format(($detail->prix ?? $detail->prix_moyen) * $qteRestante - $qteEnlevee * ($detail->prix ?? $detail->prix_moyen), '0', '', ' ') }}
                                                    fcfa</td> {{-- montant restant --}}

                                                <td> {{ $livraison->gestionnaire }} </td> {{-- Traité par --}}

                                                <td> {{ $livraison->created_at->format('d-m-Y') }}
                                                </td> {{-- Date --}}

                                                <td>
                                                    @switch($livraison->etat_livraison)
                                                        @case('EN ATTENTE')
                                                            <span class="badge bg-warning text-dark">
                                                                {{ $livraison->etat_livraison }} </span>
                                                        @break

                                                        @case('EN TRAITEMENT')
                                                            <span class="badge bg-warning">{{ $livraison->etat_livraison }}</span>
                                                        @break

                                                        @case('EN COURS LIVRAISON')
                                                            <span class="badge bg-warning">{{ $livraison->etat_livraison }}</span>
                                                        @break

                                                        @case('LIVREE')
                                                            <span class="badge bg-success">{{ $livraison->etat_livraison }}</span>
                                                        @break

                                                        @default
                                                    @endswitch
                                                </td>
                                                <td> {{ $livraison->numero_facture }} </td>
                                            </tr>
                                            @php
                                                $qteRestante -= $qteEnlevee;
                                            @endphp
                                        @endforeach
                                        {{-- Ligne de SOLDE : quantité/montant restant APRÈS tous les
                                             enlèvements ci-dessus (les lignes précédentes affichent le
                                             restant AVANT chaque enlèvement). Libellée pour la lisibilité. --}}
                                        <tr style="background-color:#eef3fa; font-weight:bold;">
                                            <td class="text-end text-muted" style="font-weight:normal; font-style:italic;">Solde</td>
                                            <td> {{ $qteRestante }} </td> {{-- quantité restante --}}

                                            <td> {{ Help::formatNombre($detail->prix, true) }} </td>
                                            {{-- prix unitaire --}}

                                            <td> {{ Help::formatNombre($qteRestante * $detail->prix, true) }}</td>
                                            {{-- montant --}}

                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>

                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                    </div>
                    <hr>

                    @if ($genFact == true)
                        <div class="container-fluid mt-3">
                            <button type="submit" class="btn btn-success align-top">Generer une facture</button>
                        </div>
                    @endif
                </form>

                {{-- ATTENTION — « Facturer la totalité » a SON PROPRE formulaire, et son
                     bouton est DEDANS.
                     Il visait auparavant ce formulaire par l'attribut « form », le bouton
                     restant à l'intérieur du formulaire de sélection : en production, le
                     clic partait malgré tout vers le formulaire englobant, qui répondait
                     « Veuillez sélectionner au moins un enlèvement ». On ne dépend plus
                     de cette résolution : le bouton est dans le formulaire qu'il soumet. --}}
                @if ($genFact == true)
                    {{-- Les deux boutons ne peuvent pas etre cote a cote : ils appartiennent
                         a deux formulaires distincts, et celui de gauche enveloppe tout le
                         tableau. On les empile — un decalage negatif les faisait se
                         chevaucher. --}}
                    <form method="POST" action="{{ route('orders.genererFactureTotale', $commande) }}"
                        class="container-fluid mt-2">
                        @csrf
                        <button type="submit" class="btn btn-primary"
                            title="Facturer d'un coup tous les bons facturables de cette commande"
                            onclick="return confirm('Générer UNE facture DGI couvrant les {{ $nbFacturables }} bon(s) facturable(s) de cette commande ?');">
                            Facturer la totalité ({{ $nbFacturables }})
                        </button>
                    </form>

                    <div class="container-fluid">
                        <small class="d-block text-muted mt-2">
                            <strong>Générer une facture</strong> ne prend que les bons cochés.
                            <strong>Facturer la totalité</strong> prend tous les bons facturables de la
                            commande, sans avoir à les cocher, et les réunit dans une seule facture.
                            Pour n'en facturer qu'un — le cas d'un bon servi partiellement —
                            utilisez le bouton « Facture DGI » de sa ligne.
                        </small>
                    </div>
                @endif

                {{-- Un formulaire par bon facturable, PLACÉ HORS du formulaire principal :
                     imbriquer deux formulaires est invalide en HTML et le navigateur
                     ignorerait le second. Les boutons des lignes, eux, sont dans le
                     tableau : ils ne peuvent pas être déplacés ici, et déclenchent donc
                     l'envoi par script plutôt qu'en s'appuyant sur l'attribut « form ». --}}
                @foreach ($details as $detail)
                    @foreach ($detail->livs as $liv)
                        @if ($liv->bon?->estFacturable())
                            <form id="factureBon{{ $liv->id_enlevement }}" method="POST"
                                action="{{ route('orders.genererFactureEnlevement', $liv->id_enlevement) }}">
                                @csrf
                            </form>
                        @endif
                    @endforeach
                @endforeach

                <br>

                {{-- @livewire('listeFacture') --}}
                {{-- <livewire:listeFacture :numero="$commande->numero" /> --}}

                <div class="col-8">
                    <span class="text">
                        <h3>Les factures</h3>
                    </span>
                    @if (!$commande->factures->isEmpty())
                        {{-- Les trois exports du tableau des factures (08/09/2026). --}}
                        <x-export-buttons table-id="tableFacturesBE"
                                          filename="factures-commande-{{ $commande->numero }}"
                                          title="Factures de la commande {{ $commande->numero }}" />
                        <table class="table table-striped" id="tableFacturesBE">
                            <thead class="thead-dark">

                                <tr>
                                    <th style="background-color: #1c57a3; color: white;" width="">
                                        Numero facture
                                    </th>
                                    <th style="background-color: #1c57a3; color: white;" width="">
                                        N° FNE (DGI)
                                    </th>
                                    <th style="background-color: #1c57a3; color: white;" width="">
                                        Statut FNE
                                    </th>
                                    <th style="background-color: #1c57a3; color: white;" width="">
                                        Date facture
                                    </th>
                                    <th style="background-color: #1c57a3; color: white;" width="">
                                        Montant
                                    </th>
                                    <th style="background-color: #1c57a3; color: white;" width="">
                                        Voir
                                    </th>
                                    <th style="background-color: #1c57a3; color: white;" width="">
                                        Télécharger
                                    </th>
                                </tr>
                            </thead>
                            <tbody>

                                @php
                                    $livraison = 1 ;
                                    $supplement = 0;
                                @endphp

                                @foreach ($commande->factures as $key =>  $facture)
                                    @php
                                        $supplement = $facture->commande?->cout_livraison_client + ($facture->commande?->tva_transport ?? 0) + ($facture->commande?->TvaCommande?->montant ?? 0) - $commande->remise;
                                        // dd($supplement, $facture->montant);
                                    @endphp

                                    @if($key > 0)
                                       @php
                                            $livraison = 0;
                                            $supplement = 0;
                                       @endphp
                                    @endif
                                    <tr>
                                        <td> {{ $facture->numero}} </td>
                                        <td>
                                            @if($facture->fne_reference)
                                                <span style="font-family: monospace; font-size: 0.85em;">{{ $facture->fne_reference }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @switch($facture->fne_status)
                                                @case('certified')
                                                    <span class="badge bg-success" title="Facture certifiée par la DGI">
                                                        Certifiée DGI
                                                    </span>
                                                    @if($facture->fne_token)
                                                        <a href="{{ $facture->fne_token }}" target="_blank" class="btn btn-sm btn-outline-success ms-1" title="Vérifier sur la plateforme FNE">
                                                            Vérifier
                                                        </a>
                                                    @endif
                                                    @break
                                                @case('failed')
                                                    <span class="badge bg-danger" title="{{ $facture->fne_error_message }}">
                                                        Échec FNE
                                                    </span>
                                                    <form action="{{ route('orders.recertifierFacture', $facture) }}" method="post" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning ms-1" title="Relancer la certification FNE">
                                                            Réessayer
                                                        </button>
                                                    </form>
                                                    @break
                                                @case('disabled')
                                                    <span class="badge bg-secondary" title="Module FNE désactivé (clé API non configurée)">
                                                        Non certifiée
                                                    </span>
                                                    <form action="{{ route('orders.recertifierFacture', $facture) }}" method="post" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary ms-1" title="Relancer la certification FNE">
                                                            Certifier
                                                        </button>
                                                    </form>
                                                    @break
                                                @default
                                                    <span class="badge bg-warning text-dark">En attente</span>
                                            @endswitch
                                        </td>
                                        <td>{{ carbon::parse($facture->created_at)->format('d-m-Y') }}</td>
                                        <td>{{ number_format($facture->montant, '0', '', ' ') }} fcfa</td>
                                        <td> <a style="text-decoration: none"
                                                href="{{ route('show.actionFacture', ['commande' => $commande, 'facture' => $facture, 'action' => 'voir', 'livraison' => $livraison]) }}"
                                                class=" text-white btn btn-primary">Voir</a> </td>
                                        <td> <a style="text-decoration: none"
                                                href="{{ route('show.actionFacture', ['commande' => $commande, 'facture' => $facture, 'action' => 'telecharger', 'livraison' => $livraison]) }}"
                                                class="text-white btn btn-primary">Telecharger</a> </td>
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>
                    @else
                        <h4>Pas de facture pour l'instant</h4>
                    @endif
                </div>
            </div>
            <!-- card-body end// -->


        </div>
        <!-- card end// -->

        <script>
            /**
             * Envoie le formulaire de facturation d'UN bon d'enlèvement.
             *
             * Le bouton se trouve dans le tableau, donc à l'intérieur du
             * formulaire de sélection ; son propre formulaire est ailleurs dans
             * la page. On le désigne par son identifiant plutôt que de compter
             * sur la remontée automatique vers le formulaire englobant.
             */
            function facturerCeBon(idFormulaire) {
                var formulaire = document.getElementById(idFormulaire);
                if (!formulaire) {
                    console.error('Facturation : formulaire introuvable ' + idFormulaire);
                    return;
                }
                var titre = 'Générer la facture DGI pour ce bon ?';
                var detail = 'Elle portera sur la <b>quantité réellement servie</b> et devra '
                    + 'ensuite être validée dans « Factures non validées ».';

                // La confirmation passe par l'assistant du back-office
                // (delete-confirm.js), le même que celui des autres boutons :
                // même fenêtre SweetAlert2, même habillage. Ce bouton y
                // échappait, son onclick ne contenant pas d'appel à confirm().
                if (typeof window.confirmDelete === 'function') {
                    window.confirmDelete({
                        mode: 'confirm',
                        title: titre,
                        html: detail,
                        confirmText: 'Oui, générer la facture',
                        onConfirm: function () {
                            formulaire.submit();
                        }
                    });
                    return;
                }

                // Repli : SweetAlert2 est servi par un CDN. S'il n'a pas pu être
                // chargé, la facturation doit rester possible.
                if (window.confirm(titre + '\n\n' + detail.replace(/<[^>]+>/g, ''))) {
                    formulaire.submit();
                }
            }
        </script>
    @endsection
