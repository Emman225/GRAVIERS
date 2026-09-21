@php
    use Illuminate\Support\Carbon;

    // Les produits éligibles sont extraits AVANT l'affichage : la vue enchaînait
    // deux boucles imbriquées avec un filtre au milieu, si bien qu'aucun compteur
    // n'était juste et qu'on ne pouvait pas détecter l'absence de produit.
    // Le compteur de ligne, lui, avançait même sur les lignes écartées : la
    // numérotation sautait des numéros (1, 2, 5, 9...).
    // Éligibilité : la ligne doit avoir été LIVRÉE.
    //
    // Le filtre portait sur « statut == 2 », qui n'a rien à voir avec la
    // livraison — l'état de livraison se lit dans etat_livraison. En base,
    // TOUTES les lignes livrées portent statut = 1 : la page ne pouvait donc
    // afficher aucun produit, et annonçait « Aucun produit éligible » à des
    // clients qui avaient pourtant été livrés. Le service après-vente était
    // inaccessible depuis le site.
    //
    // C'est la condition qu'emploie déjà la page « Retour de produit », qui,
    // elle, liste correctement les mêmes marchandises.
    $lignes = collect();
    foreach ($commandes as $commande) {
        foreach ($commande->detailCommande as $detail) {
            if ($detail->etat_livraison === 'LIVREE') {
                $lignes->push(['commande' => $commande, 'detail' => $detail]);
            }
        }
    }

    $nbTotal   = $lignes->count();
    $nbAvecTicket = $lignes->filter(fn ($l) => $l['detail']->ticket !== null)->count();
    $nbSansTicket = $nbTotal - $nbAvecTicket;
@endphp

@extends('client.main')
@section('title', 'Ticket de service après vente')

@section('cssPart')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-sav.css?v=1.0') }}">
@endsection

@section('content')
@include('client.navMobile')
    <main class="main">
        @if (session('success'))
            <div class="container mt-20">
                <div class="alert alert-success text-center" id="notify">{{ session('success') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="container mt-20">
                <div class="alert alert-danger text-center">{{ session('error') }}</div>
            </div>
        @endif

        <div class="page-header breadcrumb-wrap">
            <div class="container">
                <div class="breadcrumb">
                    <a href="{{ route('client.index') }}" rel="nofollow"><i class="fi-rs-home mr-5"></i>Accueil</a>
                    {{-- Le fil d'ariane annonçait « Boutique / Adresse », deux étapes
                         sans rapport avec cette page. --}}
                    <span></span> Mon compte
                    <span></span> Service après-vente
                </div>
            </div>
        </div>

        {{-- ===== BANDEAU ===== --}}
        <div class="sav-hero">
            <div class="sav-hero__inner">
                <div>
                    <h1 class="sav-hero__title">Ouvrir un ticket de service après-vente</h1>
                    <p class="sav-hero__subtitle">
                        Un souci avec un produit livré ? Sélectionnez-le ci-dessous, un agent vous prend en charge.
                    </p>
                </div>
                <div class="sav-hero__actions">
                    <a href="{{ route('client.mesTicketsSAV') }}" class="sav-hero__btn sav-hero__btn--plein">
                        <i class="fi-rs-time-past"></i> Suivre mes tickets
                    </a>
                    <a href="{{ route('client.monCompte') }}" class="sav-hero__btn sav-hero__btn--vide">
                        <i class="fi-rs-user"></i> Mon compte
                    </a>
                </div>
            </div>
        </div>

        <div class="container">

            {{-- ===== COMPTEURS ===== --}}
            <div class="sav-chiffres">
                <div class="sav-chiffre">
                    <div class="sav-chiffre__icone sav-chiffre__icone--total"><i class="fi-rs-box"></i></div>
                    <div>
                        <div class="sav-chiffre__valeur">{{ $nbTotal }}</div>
                        <div class="sav-chiffre__libelle">Produits livrés</div>
                    </div>
                </div>
                <div class="sav-chiffre">
                    <div class="sav-chiffre__icone sav-chiffre__icone--attente"><i class="fi-rs-add"></i></div>
                    <div>
                        <div class="sav-chiffre__valeur">{{ $nbSansTicket }}</div>
                        <div class="sav-chiffre__libelle">Sans ticket</div>
                    </div>
                </div>
                <div class="sav-chiffre">
                    <div class="sav-chiffre__icone sav-chiffre__icone--cours"><i class="fi-rs-headset"></i></div>
                    <div>
                        <div class="sav-chiffre__valeur">{{ $nbAvecTicket }}</div>
                        <div class="sav-chiffre__libelle">Ticket déjà ouvert</div>
                    </div>
                </div>
            </div>

            <div class="sav-note">
                <div class="sav-note__icone"><i class="fi-rs-info"></i></div>
                <div>
                    <p class="sav-note__titre">Comment ça marche ?</p>
                    <p>
                        Seuls les produits qui vous ont été livrés apparaissent ici. Choisissez le produit concerné,
                        décrivez votre problème, et un agent prendra contact avec vous. Vous suivrez l'avancement
                        depuis « Suivre mes tickets ».
                    </p>
                </div>
            </div>

            {{-- ===== TABLEAU ===== --}}
            <div class="sav-carte">
                <div class="sav-carte__entete">
                    <h2 class="sav-carte__titre">Vos produits livrés</h2>
                    <p class="sav-carte__aide">Sélectionnez le produit pour lequel vous rencontrez un problème.</p>
                </div>
                <div class="sav-carte__corps">
                    @if ($nbTotal > 0)
                        <div class="table-responsive">
                            <table id="listeProduitsSAV" class="sav-table">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Produit</th>
                                        <th class="text-center">Quantité</th>
                                        <th>Commande</th>
                                        <th>Date de livraison</th>
                                        <th class="text-center">Ticket</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lignes as $index => $ligne)
                                        @php
                                            $detail   = $ligne['detail'];
                                            $commande = $ligne['commande'];
                                            $ticket   = $detail->ticket;
                                            $st       = $ticket ? (int) $ticket->statut : null;
                                            // Statut de la demande de retour : 1 à traiter, 2 approuvée,
                                            // 3 refusée. Un retour REFUSÉ ne doit rien fermer — c'est même
                                            // le cas où le client a le plus besoin du service après-vente.
                                            $stRetour = $detail->retour ? (int) $detail->retour->statut : null;
                                        @endphp
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td><span class="sav-produit">{{ $detail->produit?->nom ?? '—' }}</span></td>
                                            <td class="text-center">{{ $detail->qte }}</td>
                                            <td><span class="sav-ref">{{ $commande->numero }}</span></td>
                                            {{-- data-order : sans lui, DataTables trierait « 09/12/2025 »
                                                 comme du texte, donc avant « 10/01/2024 ». --}}
                                            <td data-order="{{ Carbon::parse($detail->updated_at)->format('YmdHis') }}">
                                                {{ \Help::dateHeure($detail->updated_at) }}
                                                <span class="sav-secondaire d-block">{{ Carbon::parse($detail->updated_at)->format('à H:i:s') }}</span>
                                            </td>
                                            <td class="text-center">
                                                {{-- Statuts alignés sur ce que le code ÉCRIT réellement :
                                                     1 à la création, 2 quand le ticket est assigné à un agent,
                                                     3 quand une solution est enregistrée. Cette page annonçait
                                                     « Approuvé » pour 2 et « Réfusé » pour 3 : un client dont le
                                                     ticket venait d'être résolu lisait donc « Réfusé ». --}}
                                                @if (!$ticket)
                                                    <span class="sav-badge sav-badge--aucun">Aucun</span>
                                                @elseif ($st === 3)
                                                    <span class="sav-badge sav-badge--resolu"><i class="fi-rs-check"></i> Résolu</span>
                                                @elseif ($st === 2)
                                                    <span class="sav-badge sav-badge--cours"><i class="fi-rs-headset"></i> En traitement</span>
                                                @else
                                                    <span class="sav-badge sav-badge--nouveau"><i class="fi-rs-clock"></i> En attente</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{-- On testait la seule EXISTENCE d'une demande de retour :
                                                     un retour REFUSÉ affichait donc « Produit retourné » et
                                                     fermait définitivement l'accès au service après-vente,
                                                     alors que le client n'avait rien retourné du tout. La
                                                     page « Retour de produit » gère déjà ce cas, elle, en
                                                     proposant « Redemander » après un refus. --}}
                                                @if ($stRetour === 2)
                                                    <span class="sav-action sav-action--fait">Produit retourné</span>
                                                @elseif ($stRetour === 1)
                                                    <span class="sav-action sav-action--fait" title="Votre demande de retour est en cours d'examen.">Retour en cours</span>
                                                @elseif ($ticket)
                                                    <a href="{{ route('client.mesTicketsSAV') }}" class="sav-action">
                                                        <i class="fi-rs-eye"></i> Suivre
                                                    </a>
                                                @else
                                                    <a href="{{ route('client.infoTicketSAV', $detail) }}" class="sav-action">
                                                        <i class="fi-rs-add"></i> Ouvrir un ticket
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        {{-- Sans ce bloc, la page n'affichait qu'un tableau vide sans
                             en-tête d'explication : le client ignorait pourquoi. --}}
                        <div class="sav-vide">
                            <div class="sav-vide__icone"><i class="fi-rs-box"></i></div>
                            <div class="sav-vide__titre">Aucun produit éligible</div>
                            <p class="sav-vide__texte">
                                Un ticket ne peut être ouvert que sur un produit qui vous a été livré.
                                Dès qu'une livraison est finalisée, le produit apparaît ici.
                            </p>
                            <a href="{{ route('client.monCompte') }}" class="sav-action">
                                <i class="fi-rs-shopping-bag"></i> Voir mes commandes
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection

@section('jspart')
<script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
<script type="text/javascript">
    $(function () {
        var $table = $('#listeProduitsSAV');

        // Garde-fou : initialiser DataTables sur un tableau sans ligne déclenche
        // « Requested unknown parameter ». Le tableau n'est plus rendu du tout
        // quand il est vide, d'où le test d'existence.
        if ($table.length && $table.find('tbody tr').length > 0) {
            $table.DataTable({
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                order: [[4, 'desc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [
                    { targets: '_all', defaultContent: '-' },
                    { orderable: false, targets: [0, 6] }
                ]
            });
        }
    });
</script>
@endsection
