@php
    use Illuminate\Support\Carbon;

    // Statuts tels que le code les ÉCRIT : 1 à la création (ClientController::creationTicket),
    // 2 à l'assignation d'un agent (ticketSAVTraitements), 3 à l'enregistrement d'une
    // solution (traiterTicketSAV).
    $nbTotal   = $tickets->count();
    $nbNouveau = $tickets->filter(fn ($t) => (int) $t->statut === 1)->count();
    $nbCours   = $tickets->filter(fn ($t) => (int) $t->statut === 2)->count();
    $nbResolu  = $tickets->filter(fn ($t) => (int) $t->statut === 3)->count();
@endphp

@extends('client.main')
@section('title', 'Mes tickets SAV')

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
                    <span></span> Mon compte
                    <span></span> Mes tickets SAV
                </div>
            </div>
        </div>

        {{-- ===== BANDEAU ===== --}}
        <div class="sav-hero">
            <div class="sav-hero__inner">
                <div>
                    <h1 class="sav-hero__title">Mes tickets de service après-vente</h1>
                    <p class="sav-hero__subtitle">
                        Suivez l'avancement de vos réclamations et consultez les solutions apportées.
                    </p>
                </div>
                <div class="sav-hero__actions">
                    <a href="{{ route('client.ticketSAV') }}" class="sav-hero__btn sav-hero__btn--plein">
                        <i class="fi-rs-add"></i> Ouvrir un ticket
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
                    <div class="sav-chiffre__icone sav-chiffre__icone--total"><i class="fi-rs-headset"></i></div>
                    <div>
                        <div class="sav-chiffre__valeur">{{ $nbTotal }}</div>
                        <div class="sav-chiffre__libelle">Tickets ouverts</div>
                    </div>
                </div>
                <div class="sav-chiffre">
                    <div class="sav-chiffre__icone sav-chiffre__icone--attente"><i class="fi-rs-clock"></i></div>
                    <div>
                        <div class="sav-chiffre__valeur">{{ $nbNouveau }}</div>
                        <div class="sav-chiffre__libelle">En attente</div>
                    </div>
                </div>
                <div class="sav-chiffre">
                    <div class="sav-chiffre__icone sav-chiffre__icone--cours"><i class="fi-rs-refresh"></i></div>
                    <div>
                        <div class="sav-chiffre__valeur">{{ $nbCours }}</div>
                        <div class="sav-chiffre__libelle">En traitement</div>
                    </div>
                </div>
                <div class="sav-chiffre">
                    <div class="sav-chiffre__icone sav-chiffre__icone--resolu"><i class="fi-rs-check"></i></div>
                    <div>
                        <div class="sav-chiffre__valeur">{{ $nbResolu }}</div>
                        <div class="sav-chiffre__libelle">Résolus</div>
                    </div>
                </div>
            </div>

            {{-- ===== TABLEAU ===== --}}
            <div class="sav-carte">
                <div class="sav-carte__entete">
                    <h2 class="sav-carte__titre">Historique de mes demandes</h2>
                    <p class="sav-carte__aide">Un ticket reste « en attente » tant qu'un agent ne l'a pas pris en charge.</p>
                </div>
                <div class="sav-carte__corps">
                    @if ($nbTotal > 0)
                        <div class="table-responsive">
                            <table id="listeTicketsSAV" class="sav-table">
                                <thead>
                                    <tr>
                                        <th>N° ticket</th>
                                        <th>Produit</th>
                                        <th>Objet</th>
                                        <th>Date</th>
                                        <th class="text-center">Statut</th>
                                        <th>Solution apportée</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tickets as $ticket)
                                        @php $st = (int) $ticket->statut; @endphp
                                        <tr>
                                            <td><span class="sav-ref">{{ $ticket->numero }}</span></td>
                                            <td><span class="sav-produit">{{ $ticket->detailCommande?->produit?->nom ?? '—' }}</span></td>
                                            <td><span class="sav-texte-long">{{ $ticket->objet }}</span></td>
                                            {{-- data-order : sans lui, DataTables trierait la date
                                                 comme du texte (« 09/12/2025 » avant « 10/01/2024 »). --}}
                                            <td data-order="{{ Carbon::parse($ticket->created_at)->format('YmdHis') }}">
                                                {{ \Help::dateHeure($ticket->created_at) }}
                                                <span class="sav-secondaire d-block">{{ Carbon::parse($ticket->created_at)->format('à H:i:s') }}</span>
                                            </td>
                                            <td class="text-center">
                                                @if ($st === 3)
                                                    <span class="sav-badge sav-badge--resolu"><i class="fi-rs-check"></i> Résolu</span>
                                                @elseif ($st === 2)
                                                    <span class="sav-badge sav-badge--cours"><i class="fi-rs-headset"></i> En traitement</span>
                                                @else
                                                    <span class="sav-badge sav-badge--nouveau"><i class="fi-rs-clock"></i> En attente</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($ticket->solution_trouvee)
                                                    <span class="sav-texte-long">{{ $ticket->solution_trouvee }}</span>
                                                @else
                                                    <span class="sav-secondaire">En cours d'analyse</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="sav-vide">
                            <div class="sav-vide__icone"><i class="fi-rs-headset"></i></div>
                            <div class="sav-vide__titre">Aucun ticket pour le moment</div>
                            <p class="sav-vide__texte">
                                Vous n'avez ouvert aucune réclamation. En cas de problème avec un produit livré,
                                ouvrez un ticket : un agent vous répondra.
                            </p>
                            <a href="{{ route('client.ticketSAV') }}" class="sav-action">
                                <i class="fi-rs-add"></i> Ouvrir un ticket
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
        var $table = $('#listeTicketsSAV');

        // Garde-fou : DataTables lève « Requested unknown parameter » sur un
        // tableau sans ligne. Ici le tableau n'est pas rendu quand la liste est
        // vide, d'où le test d'existence.
        if ($table.length && $table.find('tbody tr').length > 0) {
            $table.DataTable({
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                order: [[3, 'desc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                columnDefs: [
                    { targets: '_all', defaultContent: '-' },
                    { orderable: false, targets: [5] }
                ]
            });
        }
    });
</script>
@endsection
