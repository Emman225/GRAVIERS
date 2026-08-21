{{-- @dd(Help::totalEnleveParClient(Auth::user()->client), Help::totalPaiementClient(Auth::user()->client)); --}}

@php
    use Illuminate\Support\Carbon;

    // ------------------------------------------------------------------
    // Chiffres du tableau de bord.
    // Calculés ICI, en tête de fichier, et non plus bas : la boucle des devis
    // écrase la variable $devis (@foreach($devis as $devis)), donc toute lecture
    // de la collection après cette boucle porterait sur le dernier élément.
    //
    // Aucune requête supplémentaire : les collections sont déjà chargées par le
    // contrôleur, et Help::totalEnleveSurCommande() s'appuie sur des relations
    // qu'Eloquent met en cache — l'onglet « commandes » les charge de toute façon.
    // ------------------------------------------------------------------
    $etatsClos = ['TERMINEE', 'ANNULEE'];

    // Une commande « EN ATTENTE DE PAIEMENT » est un règlement en ligne lancé puis
    // abandonné : le client a quitté la page de la passerelle. Elle n'est pas
    // confirmée, elle n'entre pas dans la file du gestionnaire, et rien n'y sera
    // jamais enlevé. La compter parmi les commandes en cours affichait au client
    // des engagements qu'il n'a pas pris — et le total « reste à enlever » incluait
    // de la marchandise qui n'a jamais été commandée pour de bon.
    //
    // Ces commandes s'accumulent vite : panierCommande les crée AVANT d'appeler la
    // passerelle, si bien qu'un aller-retour interrompu en laisse une à chaque fois.
    $etatsNonConfirmes = array_merge($etatsClos, [Help::$COMMANDE_EN_ATTENTE_PAIEMENT]);

    $commandesEnCours = $commandes->reject(fn ($c) => in_array($c->etat_commande, $etatsNonConfirmes, true));

    $resteAEnlever = $commandes
        ->reject(fn ($c) => in_array($c->etat_commande, ['ANNULEE', Help::$COMMANDE_EN_ATTENTE_PAIEMENT], true))
        ->sum(fn ($c) => max(0, $c->montantAPayer() - Help::totalEnleveSurCommande($c)));

    // Les devis encore ouverts d'un côté, ceux déjà transformés de l'autre :
    // l'onglet « Mes devis » les présente en deux tableaux distincts.
    $devisEnAttente     = $devis->where('statut', 1);
    $devisPasses        = $devis->where('statut', '!=', 1);

    // Numéro de la commande issue de chaque devis, construit depuis les commandes
    // déjà chargées : aucune requête supplémentaire. Une commande annulée n'y
    // figure pas, l'historique affiche alors un tiret.
    $commandesParDevis = $commandes->whereNotNull('devis_id')->pluck('numero', 'devis_id');
    $livraisonsEnCours  = $demandeLivraions->reject(fn ($d) => in_array($d->etat_commande, $etatsClos, true));
    $locationsEnCours   = $locations->reject(fn ($l) => in_array($l->etat_commande, $etatsClos, true));

    $dernieresCommandes = $commandes->take(5);

    // Le nombre de points est stocké dans la colonne « point » (au singulier) :
    // le pluriel du libellé testait « points », colonne inexistante, donc jamais
    // vrai — le « s » n'apparaissait pas même avec plusieurs points.
    $nbPoints = (int) ($client->point ?? 0);

    // Solde en valeur brute, pour pouvoir tester son signe et choisir le libellé.
    // false : lecture CÔTÉ CLIENT (paiements − factures), et non côté gestionnaire.
    $soldeClient = Help::soldeClientBrut($client, false);

    // Part de l'excédent qui n'attend qu'une facture. Le reste est un
    // trop-perçu : de l'argent versé au-delà de ce qui a été facturé, que
    // rien ne viendra résorber. Les deux portaient le même libellé.
    $enAttenteFacturation = $soldeClient > 0 ? Help::montantEnAttenteDeFacturation($client) : 0.0;
    $verseEnTrop = max(0, $soldeClient - $enAttenteFacturation);

    // Crédit encore disponible d'un client à terme. null si aucun plafond n'est
    // accordé — l'affichage doit alors se taire plutôt qu'annoncer 0.
    $creditDisponible = $client->client_a_terme ? $client->plafondDisponible() : null;
@endphp
@extends('client.main')
@section('title','Mon compte')

@section('cssPart')
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-tableau-bord.css?v=1.0') }}">
    {{-- La page chargeait le script DataTables sans sa feuille de styles : les
         tableaux étaient bien paginés, mais la recherche, le sélecteur du nombre
         de lignes et les boutons de pagination s'affichaient sans aucune mise
         en forme, empilés sous le tableau. --}}
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
    <style>
        /* DataTables 2.1 : les classes ont changé de nom (dt-search, dt-length,
           dt-info) — celles en dataTables_* des versions 1.x ne correspondent
           plus à rien. Simple respiration entre les commandes et le tableau. */
        .dt-container .dt-search,
        .dt-container .dt-length { margin-bottom: 10px; }
        .dt-container .dt-info { font-size: 13px; color: #6b7c8c; }
    </style>
@endsection

@section('content')
<main class="main pages">
    @include('client.navMobile')

    @if(session('errorQte'))
        <div class="alert alert-danger text-center" id="notify"> {{session('errorQte')}} </div>
    @endif
    @if(session('livree'))
        <div class="alert alert-success text-center" id="notify"> {{session('livree')}} </div>
    @endif
    @if(session('delete'))
        <div class="alert alert-info text-center" id="notify"> {{session('delete')}} </div>
    @endif
    @if(session('removeDelete'))
        <div class="alert alert-success text-center" id="notify"> {{session('removeDelete')}} </div>
    @endif
    <div class="page-content pt-30 pb-150">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="dashboard-menu">
                                <ul class="nav flex-column" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" href="#dashboard" role="tab" aria-controls="dashboard" aria-selected="false"><i class="fi-rs-settings-sliders mr-10"></i>Votre tableau de bord</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="orders-tab" data-bs-toggle="tab" href="#orders" role="tab" aria-controls="orders" aria-selected="false"><i class="fi-rs-shopping-bag mr-10"></i>Mes commandes</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="devis-tab" data-bs-toggle="tab" href="#devis" role="tab" aria-controls="devis" aria-selected="false"><i class="fi-rs-shopping-bag mr-10"></i>Mes devis</a>
                                    </li>

                                    {{-- <li class="nav-item">
                                        <a class="nav-link" id="" href="{{route('client.gestionVehicule')}}" >Mes vehicules</a>
                                    </li> --}}

                                    <li class="nav-item">
                                        <a class="nav-link" id="" href="{{route('client.retourProduitPage')}}" > Retour de produits</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="" href="{{ route('client.ticketSAV') }}"><i class="fi-rs-headset mr-10"></i>Service après-vente (SAV)</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="" href="{{ route('client.mesTicketsSAV') }}"><i class="fi-rs-time-past mr-10"></i>Mes tickets SAV (suivi)</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="delivery-tab" data-bs-toggle="tab" href="#delivery" role="tab" aria-controls="delivery" aria-selected="true"><i class="fi-rs-shopping-cart-check mr-10"></i>Demande de livraisons</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="location-tab" data-bs-toggle="tab" href="#location" role="tab" aria-controls="location" aria-selected="true"><i class="fi-rs-shopping-cart-check mr-10"></i>Demande de location</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="paiements-tab" href="{{ route('client.listePaiementCommande', 'en-attente') }}" > <i class="fi-rs-shopping-cart-check mr-10"></i>Mes paiements</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="account-detail-tab" data-bs-toggle="tab" href="#account-detail" role="tab" aria-controls="account-detail" aria-selected="true"><i class="fi-rs-user mr-10"></i>Détail du compte</a>
                                    </li>
                                    {{-- Le compte à terme est réservé aux entreprises : inutile de
                                         proposer à un particulier une démarche qu'il ne peut pas mener
                                         à son terme, faute de registre de commerce et de bilan. --}}
                                    @if ($client->type_client === 'ENTREPRISE')
                                        <li class="nav-item">
                                            <a class="nav-link" id="" href="{{route('client.demandeClientATermePage')}}" > Devenir un client à terme</a>
                                        </li>
                                    @endif

                                </ul>
                                {{-- <li class=""> --}}
                                    <form action="{{route('show.logout')}}" method="post">
                                        @csrf
                                        @method('delete')
                                        <button class="btn mt-5 d-flex align-items-center bg-secondary" style="height: 30px; background: indi">Déconnexion</button>
                                    </form>
                                    {{-- <a class="mt-5 btn bg-danger" id="" href="{{route('client.demandeClientATermePage')}}" > Deconnexion</a> --}}
                                {{-- </li> --}}
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="tab-content account dashboard-content pl-10">

                                {{-- TABLEAU DE BORD --}}
                                <div class="tab-pane fade active show" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">

                                    {{-- ===== Carte d'accueil ===== --}}
                                    <div class="tb-accueil">
                                        <div class="tb-accueil__haut">
                                            <div>
                                                <h3 class="tb-accueil__salut">
                                                    {{ (now()->format('H') < 13) ? 'Bonjour' : 'Bonsoir' }} {{ $client->display_name }} 👋
                                                </h3>
                                                <p class="tb-accueil__sous">
                                                    {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                                                </p>
                                            </div>
                                            <span class="tb-etiquette">
                                                <i class="fi-rs-{{ $client->client_a_terme ? 'credit-card' : 'shopping-cart' }}"></i>
                                                {{ $client->client_a_terme ? 'Compte à terme' : 'Compte comptant' }}
                                            </span>
                                        </div>

                                        <div class="tb-accueil__bas">
                                            {{-- Le solde était affiché tel quel, signe compris, sous
                                                 l'étiquette « Votre solde ». Un client lisant « -7 fcfa »
                                                 ne pouvait pas savoir si ce montant jouait en sa faveur
                                                 ou en sa défaveur — et le grand livre, atteint par le
                                                 lien ci-dessous, affichait le même écart en POSITIF.
                                                 Le libellé porte désormais le sens, et le nombre n'a
                                                 plus de signe. --}}
                                            <div class="tb-bloc">
                                                @if ($soldeClient < 0)
                                                    <div class="tb-bloc__libelle">Reste à payer</div>
                                                    <div class="tb-bloc__valeur">{{ number_format(abs($soldeClient), 0, ',', ' ') }} FCFA</div>
                                                @elseif ($soldeClient > 0)
                                                    {{-- « Réglé d'avance » et non « avoir » : une facture n'est
                                                         émise qu'après livraison, à la main depuis le back-office.
                                                         Un client comptant qui paie sa commande à l'avance présente
                                                         donc normalement un excédent tant qu'elle n'est pas
                                                         facturée. Parler d'avoir laisserait croire à un crédit
                                                         commercial acquis, déductible d'un prochain achat. --}}
                                                    {{-- Le libellé annonçait « En attente de facturation » dans TOUS
                                                         les cas. Or un excédent recouvre deux situations opposées :
                                                         une marchandise payée mais pas encore enlevée — la facture
                                                         viendra — ou un montant versé au-delà de ce qui a été
                                                         facturé, que rien ne résorbera. Le client lisait la même
                                                         phrase pour les deux, et attendait une facture qui n'arrivait
                                                         jamais. --}}
                                                    <div class="tb-bloc__libelle">{{ $verseEnTrop > 0 && $enAttenteFacturation <= 0 ? 'Versé en trop' : "Réglé d'avance" }}</div>
                                                    <div class="tb-bloc__valeur">{{ number_format($soldeClient, 0, ',', ' ') }} FCFA</div>
                                                    <div class="tb-bloc__note">
                                                        @if ($enAttenteFacturation > 0 && $verseEnTrop > 0)
                                                            Dont {{ number_format($verseEnTrop, 0, ',', ' ') }} FCFA versés en trop, à votre crédit
                                                        @elseif ($enAttenteFacturation > 0)
                                                            En attente de facturation
                                                        @else
                                                            À votre crédit — contactez-nous pour en disposer
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="tb-bloc__libelle">Votre compte</div>
                                                    <div class="tb-bloc__valeur">À jour</div>
                                                @endif
                                                <a href="{{ route('client.grandLivre') }}" class="tb-bloc__lien">Voir le détail</a>
                                            </div>

                                            <div class="tb-bloc">
                                                <div class="tb-bloc__libelle">Points de fidélité</div>
                                                <div class="tb-bloc__valeur">{{ $nbPoints }} point{{ $nbPoints > 1 ? 's' : '' }}</div>
                                                <div class="tb-bloc__note">Utilisables en réduction</div>
                                            </div>

                                            @if ($client->client_a_terme)
                                                <div class="tb-bloc">
                                                    {{-- C'est le crédit DISPONIBLE qui est mis en avant, et non
                                                         le plafond : c'est lui qui détermine si la prochaine
                                                         commande passera. Une commande qui le dépasse est
                                                         refusée à la validation ; autant que le client le
                                                         sache avant de remplir son panier. --}}
                                                    <div class="tb-bloc__libelle">Crédit disponible</div>
                                                    <div class="tb-bloc__valeur">
                                                        {{ $creditDisponible !== null
                                                            ? number_format($creditDisponible, 0, ',', ' ').' FCFA'
                                                            : '—' }}
                                                    </div>
                                                    <div class="tb-bloc__note">
                                                        @if ($creditDisponible !== null)
                                                            sur {{ number_format($client->plafond_credit, 0, ',', ' ') }} FCFA accordés
                                                            @if ($client->delai_paiement)
                                                                · {{ $client->delai_paiement }} jours
                                                            @endif
                                                        @else
                                                            {{ $client->delai_paiement ? 'Règlement sous '.$client->delai_paiement.' jours' : 'Plafond non défini' }}
                                                        @endif
                                                    </div>
                                                </div>
                                            @elseif ($client->type_client === 'ENTREPRISE')
                                                <div class="tb-bloc">
                                                    <div class="tb-bloc__libelle">Compte à terme</div>
                                                    <div class="tb-bloc__valeur">Non activé</div>
                                                    <a href="{{ route('client.demandeClientATermePage') }}" class="tb-bloc__lien">Faire la demande</a>
                                                </div>
                                            @else
                                                {{-- Particulier : on n'invite pas à une démarche réservée aux
                                                     entreprises, mais la tuile reste pour ne pas déséquilibrer
                                                     la carte d'accueil. --}}
                                                <div class="tb-bloc">
                                                    <div class="tb-bloc__libelle">Votre profil</div>
                                                    <div class="tb-bloc__valeur">Particulier</div>
                                                    <div class="tb-bloc__note">Paiement comptant</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ===== Chiffres clés ===== --}}
                                    <div class="tb-chiffres">
                                        <a href="#orders" data-tb-onglet="orders-tab" class="tb-chiffre">
                                            <div class="tb-chiffre__icone tb-chiffre__icone--bleu"><i class="fi-rs-shopping-bag"></i></div>
                                            <div>
                                                <div class="tb-chiffre__valeur">{{ $commandesEnCours->count() }}</div>
                                                <div class="tb-chiffre__libelle">Commandes en cours</div>
                                            </div>
                                        </a>

                                        <a href="{{ route('client.listePaiementCommande', 'en-attente') }}" class="tb-chiffre">
                                            <div class="tb-chiffre__icone tb-chiffre__icone--orange"><i class="fi-rs-time-past"></i></div>
                                            <div>
                                                <div class="tb-chiffre__valeur">{{ number_format($resteAEnlever, 0, ',', ' ') }}</div>
                                                <div class="tb-chiffre__libelle">FCFA reste à enlever</div>
                                            </div>
                                        </a>

                                        <a href="#devis" data-tb-onglet="devis-tab" class="tb-chiffre">
                                            <div class="tb-chiffre__icone tb-chiffre__icone--gris"><i class="fi-rs-document"></i></div>
                                            <div>
                                                <div class="tb-chiffre__valeur">{{ $devisEnAttente->count() }}</div>
                                                <div class="tb-chiffre__libelle">Devis en attente</div>
                                            </div>
                                        </a>

                                        <a href="#delivery" data-tb-onglet="delivery-tab" class="tb-chiffre">
                                            <div class="tb-chiffre__icone tb-chiffre__icone--vert"><i class="fi-rs-shipping-fast"></i></div>
                                            <div>
                                                <div class="tb-chiffre__valeur">{{ $livraisonsEnCours->count() }}</div>
                                                <div class="tb-chiffre__libelle">Livraisons en cours</div>
                                            </div>
                                        </a>
                                    </div>

                                    {{-- ===== Ce qui attend une action ===== --}}
                                    @if ($devisEnAttente->count() || $resteAEnlever > 0 || $locationsEnCours->count())
                                        <div class="tb-carte">
                                            <div class="tb-carte__entete">
                                                <h4 class="tb-carte__titre"><i class="fi-rs-bell"></i> À votre attention</h4>
                                            </div>
                                            <div class="tb-carte__corps">
                                                <div class="tb-todo">
                                                    @if ($devisEnAttente->count())
                                                        <a href="#devis" data-tb-onglet="devis-tab" class="tb-todo__item tb-todo__item--bleu">
                                                            <div class="tb-todo__icone"><i class="fi-rs-document"></i></div>
                                                            <div>
                                                                <p class="tb-todo__titre">{{ $devisEnAttente->count() }} devis en attente</p>
                                                                <p class="tb-todo__texte">Transformez-les en commande quand vous êtes prêt.</p>
                                                            </div>
                                                            <i class="fi-rs-angle-right tb-todo__fleche"></i>
                                                        </a>
                                                    @endif

                                                    @if ($resteAEnlever > 0)
                                                        <a href="{{ route('client.listePaiementCommande', 'en-attente') }}" class="tb-todo__item tb-todo__item--orange">
                                                            <div class="tb-todo__icone"><i class="fi-rs-credit-card"></i></div>
                                                            <div>
                                                                <p class="tb-todo__titre">{{ number_format($resteAEnlever, 0, ',', ' ') }} FCFA restent à enlever</p>
                                                                <p class="tb-todo__texte">Consultez vos paiements en attente et le détail par commande.</p>
                                                            </div>
                                                            <i class="fi-rs-angle-right tb-todo__fleche"></i>
                                                        </a>
                                                    @endif

                                                    @if ($locationsEnCours->count())
                                                        <a href="#location" data-tb-onglet="location-tab" class="tb-todo__item tb-todo__item--bleu">
                                                            <div class="tb-todo__icone"><i class="fi-rs-truck-side"></i></div>
                                                            <div>
                                                                <p class="tb-todo__titre">{{ $locationsEnCours->count() }} location{{ $locationsEnCours->count() > 1 ? 's' : '' }} en cours</p>
                                                                <p class="tb-todo__texte">Suivez l'avancement de vos demandes de location.</p>
                                                            </div>
                                                            <i class="fi-rs-angle-right tb-todo__fleche"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- ===== Dernières commandes ===== --}}
                                    <div class="tb-carte">
                                        <div class="tb-carte__entete">
                                            <h4 class="tb-carte__titre"><i class="fi-rs-shopping-bag"></i> Dernières commandes</h4>
                                            <a href="#orders" data-tb-onglet="orders-tab" class="tb-carte__lien">Tout voir</a>
                                        </div>
                                        <div class="tb-carte__corps">
                                            @if ($dernieresCommandes->count())
                                                <ul class="tb-liste">
                                                    @foreach ($dernieresCommandes as $derniere)
                                                        @php
                                                            $lien = route($derniere->est_livrable == 1
                                                                ? 'client.validationLivraisonPage'
                                                                : 'client.recuperationProduit', $derniere->id);
                                                            $classeBadge = match ($derniere->etat_commande) {
                                                                'TERMINEE'      => 'tb-badge--fini',
                                                                'EN TRAITEMENT' => 'tb-badge--cours',
                                                                'ANNULEE'       => 'tb-badge--annule',
                                                                default         => 'tb-badge--attente',
                                                            };
                                                        @endphp
                                                        <li>
                                                            <a href="{{ $lien }}" class="tb-liste__num">{{ $derniere->numero }}</a>
                                                            <div class="tb-liste__info">
                                                                <span class="tb-badge {{ $classeBadge }}">{{ $derniere->etat_commande }}</span>
                                                                <div class="tb-liste__date">
                                                                    {{ $derniere->created_at?->isoFormat('LL') }} —
                                                                    {{ $derniere->produits->count() }} produit{{ $derniere->produits->count() > 1 ? 's' : '' }}
                                                                </div>
                                                            </div>
                                                            <div class="tb-liste__montant">
                                                                {{ number_format($derniere->montantAPayer(), 0, ',', ' ') }} FCFA
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <div class="tb-vide">
                                                    <i class="fi-rs-shopping-bag"></i>
                                                    Vous n'avez pas encore passé de commande.
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ===== Raccourcis ===== --}}
                                    <div class="tb-carte">
                                        <div class="tb-carte__entete">
                                            <h4 class="tb-carte__titre"><i class="fi-rs-apps"></i> Accès rapides</h4>
                                        </div>
                                        <div class="tb-carte__corps">
                                            <div class="tb-raccourcis">
                                                <a href="{{ route('client.index') }}" class="tb-raccourci">
                                                    <i class="fi-rs-shopping-cart"></i> Commander
                                                </a>
                                                <a href="{{ route('client.demandeLivraison') }}" class="tb-raccourci">
                                                    <i class="fi-rs-shipping-fast"></i> Demander une livraison
                                                </a>
                                                <a href="{{ route('client.listePaiementCommande', 'en-attente') }}" class="tb-raccourci">
                                                    <i class="fi-rs-credit-card"></i> Mes paiements
                                                </a>
                                                <a href="{{ route('client.ticketSAV') }}" class="tb-raccourci">
                                                    <i class="fi-rs-headset"></i> Service après-vente
                                                </a>
                                                <a href="{{ route('client.retourProduitPage') }}" class="tb-raccourci">
                                                    <i class="fi-rs-refresh"></i> Retour de produit
                                                </a>
                                                <a href="{{ route('client.wishList') }}" class="tb-raccourci">
                                                    <i class="fi-rs-heart"></i> Mes souhaits
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ===== Fidélité ===== --}}
                                    <div class="tb-fidelite">
                                        <i class="fi-rs-gift"></i>
                                        <p>
                                            <strong>Comment gagner des points ?</strong><br>
                                            Chaque commande terminée vous rapporte des points de fidélité.
                                            Vous les utilisez ensuite comme réduction au moment du paiement.
                                            Vous en avez actuellement <strong>{{ $nbPoints }}</strong>.
                                        </p>
                                    </div>
                                </div>

                                {{-- COMMANDE --}}
                                <div class="tab-pane fade" id="orders" role="tabpanel" aria-labelledby="orders-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Vos commandes</h3>
                                        </div>


                                        <div class="card-body">
                                            <a href="{{route('client.exportCommande')}}">Télécharger la liste des commandes</a>
                                            <div class="table-responsive">
                                                <table class="table" id="liste">
                                                    <thead>
                                                        <tr>
                                                            <th></th>
                                                            <th>Numéro</th>
                                                            <th>Date commande</th>
                                                            <th>Statut</th>

                                                            <th>Total à payer</th>
                                                            <th>Déjà enlevé</th>
                                                            <th>Reste à enlever</th>

                                                            <th></th>


                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($commandes as $commande)
                                                        <tr>
                                                            {{-- Voir --}}
                                                            <td>
                                                                <a href="{{route($commande->est_livrable == 1 ? 'client.validationLivraisonPage' : 'client.recuperationProduit' ,$commande->id)}}">
                                                                    <span style="cursor:pointer;"><i class="fa-solid fa-eye"></i></span>
                                                                </a>
                                                            </td>

                                                            {{-- numero --}}
                                                            <td>
                                                                <a href="{{route($commande->est_livrable == 1 ? 'client.validationLivraisonPage' : 'client.recuperationProduit' ,$commande->id)}}">{{$commande->numero}}<br>  <span class="text-muted"> ({{$commande->produits->count()}} produit{{($commande->produits->count() > 1) ? 's' :''}}) </span> </a>
                                                            </td>

                                                            {{-- date --}}
                                                            <td>{{$commande->created_at->isoFormat('LL') .' à '. $commande->created_at->format('H:i')}}</td>

                                                            {{-- statut --}}
                                                            <td>
                                                                @switch($commande->etat_commande)
                                                                    @case('EN ATTENTE')
                                                                    <span class="badge bg-secondary">{{$commande->etat_commande}}</span>
                                                                        @break
                                                                    @case('EN TRAITEMENT')
                                                                    <span class="badge bg-warning">{{$commande->etat_commande}}</span>
                                                                        @break
                                                                    @case('TERMINEE')
                                                                    <span class="badge bg-success">{{$commande->etat_commande}}</span>
                                                                        @break

                                                                    @default

                                                                @endswitch

                                                            </td>

                                                            {{-- total (net depuis les lignes : cf. Commande::montantAPayer) --}}
                                                            <td>
                                                                {{number_format($commande->montantAPayer(),0,'',' ')}} fcfa
                                                            </td>

                                                            {{-- Déjà enlevé : la MARCHANDISE retirée, prix de la ligne
                                                                 × quantité servie.

                                                                 La colonne affichait auparavant totalEnleveSurCommande(),
                                                                 qui part de « TVA + livraison − remise » : une commande dont
                                                                 rien n'avait été retiré annonçait 4 900 F enlevés (900 de TVA
                                                                 + 4 000 de livraison). --}}
                                                            @php
                                                                $dejaEnleve = Help::marchandiseEnleveeSurCommande($commande);
                                                                $resteAEnlever = max(0, $commande->montantHT() - $dejaEnleve);
                                                            @endphp
                                                            <td>
                                                                {{number_format($dejaEnleve,0,'',' ')}} fcfa
                                                            </td>

                                                            {{-- Reste à enlever. Les deux colonnes s'additionnent désormais
                                                                 pour donner le montant HT de la marchandise commandée ; le
                                                                 « Total », lui, ajoute la livraison et la TVA. --}}
                                                            <td>
                                                                {{number_format($resteAEnlever,0,'',' ')}} fcfa
                                                            </td>

                                                            <td>
                                                                <a href="{{route('client.listeFacture',$commande)}}" class="btn-small d-block">Facture</a>
                                                                @php $demandeAnnul = $commande->derniereDemandeAnnulation; @endphp
                                                                @if ($commande->etat_commande === 'ANNULEE')
                                                                    <span class="badge bg-danger">Annulée</span>
                                                                @elseif ($demandeAnnul && !$demandeAnnul->est_traite)
                                                                    {{-- Demande en cours : proposer « Annuler » à nouveau
                                                                         laissait croire que rien n'était parti. --}}
                                                                    <span class="badge bg-warning text-dark d-block mt-1">Annulation demandée</span>
                                                                    <small class="text-muted d-block">en cours d'examen</small>
                                                                @elseif ($demandeAnnul && $demandeAnnul->est_traite && (int) $demandeAnnul->decision !== 1)
                                                                    {{-- Refus : le client n'en était informé QUE par courriel.
                                                                         Un message manqué, et il ne savait plus rien — ni que
                                                                         sa demande avait été tranchée, ni pourquoi. --}}
                                                                    <span class="badge bg-danger d-block mt-1">Annulation refusée</span>
                                                                    @if ($demandeAnnul->note)
                                                                        <small class="text-muted d-block">Motif : {{ $demandeAnnul->note }}</small>
                                                                    @endif
                                                                    @if ($commande->etat_commande !== 'TERMINEE')
                                                                        <a href="{{ route('client.demandeAnnulationCommande', $commande->numero) }}"
                                                                           class="btn-small d-block text-danger">Redemander</a>
                                                                    @endif
                                                                @elseif ($commande->etat_commande !== 'TERMINEE')
                                                                    <a href="{{ route('client.demandeAnnulationCommande', $commande->numero) }}"
                                                                       class="btn-small d-block text-danger">Annuler</a>
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

                                {{-- DEVIS
                                     Deux tableaux séparés plutôt qu'une liste unique : les devis
                                     encore ouverts, sur lesquels le client peut agir, et l'historique
                                     de ceux déjà transformés, qui ne se consultent plus que pour
                                     mémoire. Mélangés, un devis commandé se confondait avec un devis
                                     à traiter, la seule différence étant une pastille de couleur. --}}
                                <div class="tab-pane fade" id="devis" role="tabpanel" aria-labelledby="devis-tab">

                                    {{-- ----- Devis en attente ----- --}}
                                    <div class="card mb-4">
                                        <div class="card-header d-flex align-items-center justify-content-between">
                                            <h3 class="mb-0">Mes devis en attente</h3>
                                            <span class="badge bg-secondary">{{ $devisEnAttente->count() }}</span>
                                        </div>
                                        <div class="card-body">
                                            @if ($devisEnAttente->isEmpty())
                                                <p class="text-muted mb-0">Vous n'avez aucun devis en attente.</p>
                                            @else
                                            <div class="table-responsive">
                                                <table class="table" id="listeDevis">
                                                    <thead>
                                                        <tr>
                                                            <th>Numéro</th>
                                                            <th>Enregistré le</th>
                                                            <th>Total</th>
                                                            <th></th>
                                                            <th></th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {{-- La variable de boucle ne doit pas porter le nom de la
                                                             collection : après un tel bloc, $devis ne désignerait plus
                                                             la liste mais le dernier de ses éléments, et tout calcul
                                                             placé plus bas dans la page porterait sur ce résidu. --}}
                                                        @foreach($devisEnAttente as $unDevis)
                                                        <tr>
                                                            <td>
                                                                {{$unDevis->numero}} <span class="text-muted"> ({{$unDevis->detailDevis->where('deleted_at',null)->count()}} produit{{($unDevis->detailDevis->where('deleted_at',null)->count() > 1) ? 's' :''}}) </span>
                                                                @if(!empty($unDevis->libelle))
                                                                    <br><small class="text-muted"><i class="fi-rs-label"></i> {{ $unDevis->libelle }}</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                {{$unDevis->created_at->isoFormat('LL') .' à '.$unDevis->created_at->format('H:i')}}
                                                            </td>
                                                            <td>
                                                                {{number_format($unDevis->montantAPayer(),0,'','.')}} fcfa
                                                            </td>
                                                            <td>
                                                                <a href="{{route('devis.modePaiement',$unDevis)}}" class="btn-small d-block">Commander</a>
                                                            </td>
                                                            <td>
                                                                <a href="{{route('client.factureDevis',$unDevis->numero)}}" class="btn-small d-block">Devis</a>
                                                            </td>
                                                            <td>
                                                                <a href="{{route('devis.editDevis',$unDevis)}}" class="btn-small d-block">Modifier</a>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ----- Historique : devis passés en commande ----- --}}
                                    <div class="card">
                                        <div class="card-header d-flex align-items-center justify-content-between">
                                            <h3 class="mb-0">Devis passés en commande</h3>
                                            <span class="badge bg-success">{{ $devisPasses->count() }}</span>
                                        </div>
                                        <div class="card-body">
                                            @if ($devisPasses->isEmpty())
                                                <p class="text-muted mb-0">Aucun de vos devis n'a encore été transformé en commande.</p>
                                            @else
                                            <div class="table-responsive">
                                                <table class="table" id="historiqueDevis">
                                                    <thead>
                                                        <tr>
                                                            <th>Numéro</th>
                                                            <th>Enregistré le</th>
                                                            <th>Total</th>
                                                            <th>Commande</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($devisPasses as $unDevis)
                                                        <tr>
                                                            <td>
                                                                {{$unDevis->numero}} <span class="text-muted"> ({{$unDevis->detailDevis->where('deleted_at',null)->count()}} produit{{($unDevis->detailDevis->where('deleted_at',null)->count() > 1) ? 's' :''}}) </span>
                                                                @if(!empty($unDevis->libelle))
                                                                    <br><small class="text-muted"><i class="fi-rs-label"></i> {{ $unDevis->libelle }}</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                {{$unDevis->created_at->isoFormat('LL') .' à '.$unDevis->created_at->format('H:i')}}
                                                            </td>
                                                            <td>
                                                                {{number_format($unDevis->montantAPayer(),0,'','.')}} fcfa
                                                            </td>
                                                            <td>
                                                                {{-- La commande issue de ce devis : le client suit ainsi
                                                                     ce qu'est devenue sa demande. --}}
                                                                @if (!empty($commandesParDevis[$unDevis->id]))
                                                                    <span class="badge bg-success">{{ $commandesParDevis[$unDevis->id] }}</span>
                                                                @else
                                                                    <span class="text-muted">—</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <a href="{{route('client.factureDevis',$unDevis->numero)}}" class="btn-small d-block">Devis</a>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- VEHICULE --}}
                                {{-- <div class="tab-pane fade" id="vehicule" role="tabpanel" aria-labelledby="vehicule-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Détails du compte</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-success text-center" style="display: none" id="success"></div>
                                            <div class="alert alert-danger text-center" style="display: none" id="result"></div>
                                            <form method="post" id="formVehicule" action="{{route('client.update')}}">
                                                @csrf
                                                <div class="row">
                                                    <div class="form-group col-md-6">
                                                        <label>type <span class="required"></span></label>
                                                        <select  style="border: 1px solid black" class="form-control" name="type" value="" type="text">
                                                            <option value="">Selectionnez un type...</option>
                                                            @foreach ($types as $type )
                                                                <option value="{{$type->id}}"> {{$type->libelle}} </option>
                                                            @endforeach
                                                        </select>
                                                        <span class="text-danger"  id="type"></span>

                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label>Marque<span class="required"></span></label>
                                                        <input style="border: 1px solid black" value="" class="form-control" name="marque" />
                                                        <span class="text-danger" id="errorMarque"></span>
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Immatriculation <span class="required">*</span></label>
                                                        <input style="border: 1px solid black" value="" class="form-control" name="matricule" type="text" />
                                                        <span class="text-danger" id="matricule"></span>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label>Modèle <span class="required">*</span></label>
                                                        <input style="border: 1px solid black" value=" " class="form-control" name="modele" type="text" />
                                                        <span class="text-danger" id="modele"></span>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label>Capacité <span class="required">*</span></label>
                                                        <input style="border: 1px solid black" class="form-control" value=" " name="capacite" type="number" />
                                                        <span class="text-danger" id="capacite"></span>
                                                    </div>

                                                    <button type="submit" class="btn btn-primary">Enregistrer</button>

                                                    <div class="col-md-12">

                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div> --}}



                                {{-- DEMANDE DE LIVRAISON --}}
                                <div class="tab-pane fade" id="delivery" role="tabpanel" aria-labelledby="delivery-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Vos demandes de livraison</h3>
                                        </div>
                                        <a href="{{route('client.exportDemandeDeLivraison')}}">Téléchargez la liste des demandes de livraison</a>
                                        <a class="btn btn-primary" href="{{route('client.demandeLivraison')}}">Demander une livraison</a>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table" id="liste">
                                                    <thead>
                                                        <tr>
                                                            <th>Numéro</th>
                                                            <th>Produits</th>
                                                            <th>Date de demande</th>
                                                            <th>Statut</th>
                                                            {{-- <th>Détail de la livraison</th> --}}
                                                            <th>montant</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($demandeLivraions as $demande)
                                                        {{-- @dd($demande) --}}

                                                        {{-- @dd($demande) --}}
                                                            {{-- @foreach ($demandes as $livraison ) --}}
                                                                <tr>
                                                                    <td> {{$demande->numero}} <span class="text-muted">  </span> </td>
                                                                    <td>
                                                                        @foreach ($demande->detailLivraison as $detail )
                                                                            {{$detail->nom_produit}} <br>
                                                                        @endforeach
                                                                    </td>
                                                                    <td>{{Carbon::parse($demande->created_at)->format('d-m-Y')}}</td>
                                                                    <td>
                                                                        @switch($demande->etat_commande)
                                                                            @case('EN ATTENTE')
                                                                            <span class="badge bg-secondary">{{$demande->etat_commande}}</span>
                                                                                @break
                                                                            @case('EN TRAITEMENT')
                                                                            <span class="badge bg-warning">{{$demande->etat_commande}}</span>
                                                                                @break
                                                                            @case('TERMINEE')
                                                                            <span class="badge bg-success">{{$demande->etat_commande}}</span>
                                                                                @break

                                                                            @default

                                                                        @endswitch

                                                                    </td>

                                                                    <td>{{number_format($demande->montantTotal,0,'','.')}} fcfa
                                                                        {{-- <small class="text-muted"> (Contient {{$commande->produits->count()}} produit{{($commande->produits->count()>1)? 's' : ''}})  </small> --}}
                                                                    </td>

                                                                    <td><a href="{{route('client.detaiDemandeDeLivraison',$demande)}}" class="btn-small d-block">Détails</a></td>
                                                                </tr>
                                                            {{-- @endforeach --}}
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- DEMANDE DE LOCATION --}}
                                <div class="tab-pane fade" id="location" role="tabpanel" aria-labelledby="location-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Mes demandes de location</h3>
                                            <a href="{{route('client.exportLocation')}}">Télécharger la liste des demandes de location</a>
                                        </div>
                                        @if ($locations->isEmpty())
                                            <H2>Vous n'avez pas de demande de location en cours</H2>
                                        @else
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table" id="liste">
                                                    <thead>
                                                        <tr>
                                                            <th>Numéro</th>
                                                            <th>Produits</th>
                                                            <th>Date de demande</th>
                                                            <th>Statut</th>
                                                            <th>montant</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($locations as $location)
                                                        {{-- @dd($demande) --}}

                                                        {{-- @dd($demande) --}}
                                                            {{-- @foreach ($demandes as $livraison ) --}}

                                                                <tr>
                                                                    <td> {{$location->numero}} <span class="text-muted">  </span> </td>
                                                                    <td>
                                                                        @foreach ($location->detailLocation as $detail )
                                                                            {{$detail->produit?->nom}} <br>
                                                                        @endforeach
                                                                    </td>
                                                                    <td>{{Carbon::parse($location->created_at)->format('d-m-Y')}}</td>
                                                                    <td>
                                                                        @switch($location->etat_commande)
                                                                            @case('EN ATTENTE')
                                                                            <span class="badge bg-secondary">{{$location->etat_commande}}</span>
                                                                                @break
                                                                            @case('EN TRAITEMENT')
                                                                            <span class="badge bg-warning">{{$location->etat_commande}}</span>
                                                                                @break
                                                                            @case('TERMINEE')
                                                                            <span class="badge bg-success">{{$location->etat_commande}}</span>
                                                                                @break

                                                                            @default

                                                                        @endswitch

                                                                    </td>

                                                                    <td>{{number_format($location->montant_total,0,'',' ')}} fcfa
                                                                        {{-- <small class="text-muted"> (Contient {{$commande->produits->count()}} produit{{($commande->produits->count()>1)? 's' : ''}})  </small> --}}
                                                                    </td>

                                                                    <td><a href="{{route('client.detailDeLocation',$location)}}" class="btn-small d-block">Détails</a></td>
                                                                </tr>
                                                            {{-- @endforeach --}}
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>


                                {{-- MES PAIEMENTS --}}
                                {{-- <div class="tab-pane fade" id="paiements" role="tabpanel" aria-labelledby="paiements-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Mes paiements</h3>
                                            <a href="{{route('client.exportLocation')}}">Télécharger la liste de mes paiements</a>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <label for="paiement" class="btn btn-success"  id="button-payer" style="display: none">Payer <br><span class="fw-bold" id="span-payer"></span></label>
                                            </div>
                                            <div class="col-md-5  d-flex justify-content-around">
                                                <div class="form-check">
                                                    <input class="form-check-input" onclick="listePaiement({{2}})" type="radio" name="listeMontant" id="flexRadioDefault1" checked>
                                                    <label class="form-check-label" for="flexRadioDefault1">
                                                        Paiement non soldé
                                                    </label>
                                                </div>

                                                <div class="form-check">
                                                    <input class="form-check-input" onclick="listePaiement({{1}})" type="radio" name="listeMontant" id="flexRadioDefault2">
                                                    <label class="form-check-label" for="flexRadioDefault2">
                                                        Paiement soldé
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <form action="{{route('client.afficherMontant')}}" method="post">
                                            @csrf
                                            <button type="submit" id="paiement" style="display:none"></button>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table" id="listePaiements">
                                                        <thead>
                                                            <tr>
                                                                <th></th>
                                                                <th>code</th>
                                                                <th>libelle</th>
                                                                <th>Numero commande</th>
                                                                <th>Date commande</th>
                                                                <th>Numero facture</th>
                                                                <th>Date facture</th>
                                                                <th>montant</th>
                                                                <th>Créé le</th>
                                                                <!-- <th>Statut</th> -->
                                                            </tr>
                                                        </thead>
                                                        <tbody id="tbody">
                                                            @foreach($paiements as $paiement)
                                                                <tr>
                                                                    <td>
                                                                        <input type="checkbox" onclick="payer({{$paiement->id}})" value="{{$paiement->id}}" class="form-check-input checkMontant"
                                                                        name="paiements[]" id="{{$paiement->montant_total}}">
                                                                    </td>
                                                                    <td> {{$paiement->code}} </td>
                                                                    <td> {{$paiement->libelle}} </td>
                                                                    <td> {{$paiement->devis?->numero}} </td>
                                                                    <td> {{$paiement->devis?->created_at->format('d-m-Y à H:i')}} </td>
                                                                    <td></td>
                                                                    <td></td>
                                                                    <td> {{number_format($paiement->montant_total,'0','',' ')}} fcfa </td>
                                                                    <td> {{($paiement->created_at)->format('d-m-Y à H:i')}} </td>
                                                                    <!-- <td> {{($paiement->statut == 1) ? 'Payé' : 'En attente'}} </td> -->
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </form>

                                    </div>
                                </div> --}}


                                {{-- <div class="tab-pane fade" id="address" role="tabpanel" aria-labelledby="address-tab">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="card mb-3 mb-lg-0">
                                                <div class="card-header">
                                                    <h3 class="mb-0">Billing Address</h3>
                                                </div>
                                                <div class="card-body">
                                                    <address>
                                                        3522 Interstate<br />
                                                        75 Business Spur,<br />
                                                        Sault Ste. <br />Marie, MI 49783
                                                    </address>
                                                    <p>New York</p>
                                                    <a href="#" class="btn-small">Edit</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0">Shipping Address</h5>
                                                </div>
                                                <div class="card-body">
                                                    <address>
                                                        4299 Express Lane<br />
                                                        Sarasota, <br />FL 34249 USA <br />Phone: 1.941.227.4444
                                                    </address>
                                                    <p>Sarasota</p>
                                                    <a href="#" class="btn-small">Edit</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> --}}

                                {{-- INFORMATION DU CLIENT  --}}
                                <div class="tab-pane fade" id="account-detail" role="tabpanel" aria-labelledby="account-detail-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Détails du compte</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="post" id="form" action="{{route('client.update')}}">
                                                @csrf
                                                <div class="row">
                                                    @if($client->type_client == "PARTICULIER")
                                                        <div class="form-group col-md-6">
                                                            <label>Nom <span class="required"></span></label>
                                                            <input class="form-control" name="nom" value="{{$client->nom}}" type="text" />
                                                            @error("nom")
                                                            <span class="text-danger"> {{$message}}</span>
                                                            @enderror
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label>Prénom<span class="required"></span></label>
                                                            <input value="{{$client->prenom}}" class="form-control" name="prenom" />
                                                            @error("prenom")
                                                            <span class="text-danger"> {{$message}}</span>
                                                            @enderror
                                                        </div>
                                                    @else
                                                        <div class="form-group col-md-6">
                                                            <label>Raison sociale<span class="required"></span></label>
                                                            <input value="{{$client->nom}}" class="form-control" name="raisonSociale" required />
                                                            @error("prenom")
                                                            <span class="text-danger"> {{$message}}</span>
                                                            @enderror
                                                        </div>
                                                    @endif
                                                    <div class="form-group col-md-12">
                                                        <label>Conctact1 <span class="required">*</span></label>
                                                        <input value="{{$client->contact1}}" class="form-control" name="contact1" type="text" />
                                                        @error("contact1")
                                                           <span class="text-danger"> {{$message}}</span>
                                                        @enderror
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Conctact2 <span class="required">*</span></label>
                                                        <input value="{{$client->contact1}}" class="form-control" name="contact2" type="text" />
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Adresse Email  <span class="required">*</span></label>
                                                        <input class="form-control" disabled value="{{$client->user?->email}}" name="email" type="email" />
                                                        @error("email")
                                                           <span class="text-danger"> {{$message}}</span>
                                                        @enderror
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Ville <span class="required"></span></label>
                                                        <select style="border: solid 1px grey" class="form-control " required name="ville">
                                                            <option value="">Selectionnez une ville...</option>
                                                            @foreach ($villes as $ville)
                                                                <option @selected($ville->id == $client->user?->ville_id) value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Adresse   <span class="required">*</span></label>
                                                        <input class="form-control" value="{{$client->user?->adresse}}" name="adresse" type="text" />
                                                    </div>
                                                    {{-- ********************* NCC et RCC ***************** --}}
                                                    @if($client->type_client == "ENTREPRISE")
                                                        <div class="form-group col-md-12">
                                                            <span>Registre de commerce</span>
                                                            <input style="border: solid 1px grey;" type="text" id="rccm" value="{{ $client->rccm_clt }}" name="rccm" required placeholder="RCCM" />
                                                        </div>
                                                        <div class="form-group col-md-12">
                                                            <span>N° Compte contribuable</span>
                                                            <input style="border: solid 1px grey;" type="text" id="ncc" value="{{ $client->ncc_clt }}" name="ncc" required placeholder="NCC" />
                                                        </div>
                                                    @endif
                                                    {{-- ********************* FIN ************************ --}}
                                                    <div class="form-group col-md-12">
                                                        <label>Code parrain <span class="required">*</span></label>
                                                        <input {{$client->code_parrain ? 'disabled' : ''}} class="form-control" value="{{$client->code_parrain}}"  name="code" type="text" />
                                                    </div>
                                                    <div class="form-group col-md-12 position-relative">
                                                        <label>Mot de passe actuel <span class="required">*</span></label>
                                                        <input class="form-control" name="password" id="password" type="password" />
                                                        <span class="position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;" id="oeil" onclick="togglePassword()"><i class="fa-solid fa-eye-slash"></i></span>
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Nouveau mot de passe <span class="required">*</span></label>
                                                        <input  class="form-control" id="pass1" name="newPassword" type="password" />
                                                        <span class="text-danger" id="erreurCourt"></span>
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Confirmez le nouveau mot de passe <span class="required">*</span></label>
                                                        <input class="form-control" id="pass2" name="confirmPassword" type="password" />
                                                        <span class="text-danger" id="erreur"></span>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <button type="submit" class="btn btn-fill-out submit font-weight-bold" id="submit" >Sauvegarder les modification</button>
                                                    </div>
                                                    <div class="col-md-12">
                                                        @if (Auth::user()->statut == 1)
                                                            <a class="text-danger" onclick="return confirm('Voulez-vous vraiment supprimer votre compte?')" href="{{route('client.supprimerCompte')}}" class="text-muted"><i class="fi-rs-sign-out mr-10 text-white"></i>Supprimer mon compte</a>
                                                        @else
                                                            <a class="text-danger" onclick="return confirm('Voulez-vous vraiment la demande de suppression ?')" href="{{route('client.supprimerCompte')}}" class="text-muted"><i class="fi-rs-sign-out mr-10 text-white"></i>Annuler la demande de suppression</a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

{{-- =================================================================
     PREMIUM STYLES — Compte client (injection CSS sans modification HTML)
     ================================================================= --}}
<style>
    /* ===== PAGE BG ===== */
    main.pages { background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); }

    /* ===== SIDEBAR =====
       Ce bloc décrivait un menu CLAIR (fond blanc, texte gris) alors que la
       feuille premium-client-account.css impose un fond SOMBRE en !important :
       seul le fond sombre s'appliquait, et le texte gris devenait illisible
       par-dessus. Les valeurs ci-dessous sont alignées sur le fond réellement
       affiché, avec un texte et des icônes en blanc. */
    .dashboard-menu {
        background: #1e2436;
        border: 1px solid #2a3047;
        border-radius: 16px;
        padding: 14px 10px;
        box-shadow: 0 10px 25px rgba(10,37,64,0.15);
        position: sticky;
        top: 20px;
    }
    .dashboard-menu .nav { gap: 4px; }
    .dashboard-menu .nav-link {
        display: flex !important;
        align-items: center;
        gap: 10px;
        padding: 11px 14px !important;
        border-radius: 10px !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        font-size: 0.9rem !important;
        transition: all 0.15s ease !important;
        background: transparent !important;
        border: 0 !important;
        text-decoration: none !important;
    }
    .dashboard-menu .nav-link i {
        font-size: 16px !important;
        color: #ffffff !important;
        transition: color 0.15s ease;
        flex-shrink: 0;
    }
    /* Survol : seul le FOND change, le texte et l'icône restant blancs.
       L'ancien couple (fond gris très clair, texte bleu) était prévu pour un
       menu clair et se voyait à peine sur le fond sombre. */
    .dashboard-menu .nav-link:hover {
        background: #2a3047 !important;
        color: #ffffff !important;
    }
    .dashboard-menu .nav-link:hover i { color: #ffffff !important; }
    .dashboard-menu .nav-link.active {
        background: linear-gradient(135deg, #1c57a3, #134380) !important;
        color: #ffffff !important;
        box-shadow: 0 8px 18px rgba(28,87,163,0.25);
    }
    .dashboard-menu .nav-link.active i { color: #ffffff; }

    .dashboard-menu form .btn {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        width: 100% !important;
        padding: 11px 14px !important;
        margin-top: 10px !important;
        background: #fef2f2 !important;
        border: 1.5px solid #fecaca !important;
        color: #b91c1c !important;
        font-weight: 700 !important;
        font-size: 0.9rem !important;
        border-radius: 10px !important;
        transition: all 0.15s ease !important;
    }
    .dashboard-menu form .btn:hover {
        background: #ef4444 !important;
        border-color: #ef4444 !important;
        color: #ffffff !important;
        box-shadow: 0 6px 14px rgba(239,68,68,0.30);
    }
    .dashboard-menu form .btn::before {
        content: "↪";
        font-size: 1.1rem;
    }

    /* ===== CARDS DES ONGLETS ===== */
    .dashboard-content .card {
        background: #ffffff;
        border: 1px solid #e5e7eb !important;
        border-radius: 16px !important;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        margin-bottom: 24px;
    }
    .dashboard-content .card-header {
        padding: 18px 22px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        background: linear-gradient(to right, #f8fafc, #ffffff) !important;
    }
    .dashboard-content .card-header h3,
    .dashboard-content .card-header h4 {
        color: #0a2540 !important;
        font-weight: 700 !important;
        font-size: 1.15rem !important;
        margin: 0;
    }
    .dashboard-content .card-header h3 + p { color: #6b7280; font-size: 0.92rem; margin-top: 4px; }
    .dashboard-content .card-body { padding: 22px !important; }

    /* Welcome dashboard inner highlight */
    #dashboard .card .container h3.bg-primary {
        background: linear-gradient(135deg, #1c57a3, #134380) !important;
        padding: 14px 20px !important;
        border-radius: 12px !important;
        margin: 0 22px 18px !important;
        font-size: 1.1rem !important;
        font-weight: 700;
        box-shadow: 0 8px 18px rgba(28,87,163,0.25);
    }

    /* ===== TABLES ===== */
    .dashboard-content table.table thead tr,
    .dashboard-content table.table thead th {
        background: #f9fafb !important;
        color: #374151 !important;
        font-weight: 700 !important;
        font-size: 0.78rem !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid #e5e7eb !important;
        border-top: 0 !important;
        padding: 12px 10px !important;
    }
    .dashboard-content table.table tbody td {
        padding: 14px 10px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        vertical-align: middle !important;
        font-size: 0.88rem;
    }
    .dashboard-content table.table tbody tr:hover { background: #fafbfc; }

    /* Liens de download/export */
    .dashboard-content .card-body > a[href*="export"] {
        display: inline-flex !important;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: #ecfdf5;
        border: 1px solid #d1fae5;
        color: #065f46 !important;
        font-weight: 600;
        font-size: 0.85rem;
        border-radius: 10px;
        text-decoration: none;
        transition: all 0.15s ease;
        margin-bottom: 14px;
    }
    .dashboard-content .card-body > a[href*="export"]:hover {
        background: #10b981;
        color: #ffffff !important;
        border-color: #10b981;
    }
    .dashboard-content .card-body > a[href*="export"]::before {
        content: "⬇";
        font-size: 14px;
    }

    /* ===== INPUTS ===== */
    .dashboard-content input.form-control,
    .dashboard-content select.form-control,
    .dashboard-content textarea.form-control {
        padding: 11px 14px !important;
        border: 1.5px solid #e5e7eb !important;
        border-radius: 10px !important;
        background: #ffffff !important;
        font-size: 0.92rem !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        height: auto !important;
    }
    .dashboard-content input.form-control:focus,
    .dashboard-content select.form-control:focus,
    .dashboard-content textarea.form-control:focus {
        border-color: #ea580c !important;
        box-shadow: 0 0 0 3px rgba(234,88,12,0.12) !important;
        outline: none !important;
    }

    /* ===== BOUTONS principaux ===== */
    .dashboard-content button[type="submit"]:not(.dashboard-menu *),
    .dashboard-content .btn-primary {
        background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%) !important;
        border: 0 !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        padding: 11px 22px !important;
        border-radius: 10px !important;
        box-shadow: 0 8px 18px rgba(234,88,12,0.30) !important;
        transition: all 0.18s ease !important;
    }
    .dashboard-content button[type="submit"]:not(.dashboard-menu *):hover,
    .dashboard-content .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(234,88,12,0.42) !important;
    }

    /* Badges existants */
    .dashboard-content .badge.bg-secondary { background: #f3f4f6 !important; color: #4b5563 !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }
    .dashboard-content .badge.bg-warning { background: #fef3c7 !important; color: #92400e !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }
    .dashboard-content .badge.bg-primary { background: #dbeafe !important; color: #1e40af !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }
    .dashboard-content .badge.bg-success { background: #d1fae5 !important; color: #065f46 !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }
    .dashboard-content .badge.bg-danger { background: #fee2e2 !important; color: #991b1b !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }

    /* ===== POINTS HIGHLIGHT (dashboard) ===== */
    #dashboard p.fw-bold span.text-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #ffffff !important;
        border-radius: 999px;
        font-weight: 800;
        margin: 0 4px;
        box-shadow: 0 4px 10px rgba(251,191,36,0.30);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 767px) {
        .dashboard-menu { position: static; }
    }
</style>

@section('jspart')
    <script>
        /**
         * Tuiles et liens du tableau de bord qui renvoient vers un autre onglet.
         *
         * Un simple data-bs-toggle="tab" ne suffit pas : Bootstrap 5 n'active son
         * composant Tab que si le déclencheur se trouve dans un conteneur .nav ou
         * [role="tablist"]. Les tuiles vivent dans une grille, donc le clic ne
         * produisait rien. On déclenche ici le lien correspondant du menu latéral,
         * qui lui est bien dans le .nav : l'onglet ET le menu restent cohérents.
         */
        document.addEventListener('click', function (e) {
            var declencheur = e.target.closest('[data-tb-onglet]');
            if (!declencheur) { return; }

            var lienMenu = document.getElementById(declencheur.dataset.tbOnglet);
            if (!lienMenu) { return; }

            e.preventDefault();
            lienMenu.click();
            document.querySelector('.dashboard-content')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        let form = document.getElementById('form');
        form.addEventListener('submit', function(e) {
            let pass1 = document.getElementById('pass1')
            let pass2 = document.getElementById('pass2')
            let erreur = document.getElementById('erreur')
            let erreurCourt = document.getElementById('erreurCourt')


            // alert(password.value.length)

            if(pass1>0){
                if (pass1.value.length < 4) {
                    // alert(pass1.value)
                    erreurCourt.innerHTML = "Le mot de passe doit être au moins 4 caractères";
                    e.preventDefault();
                } else if (pass1.value.trim() != pass2.value.trim()) {
                    erreurCourt.innerHTML = "";
                    erreur.innerHTML = "Les mots de passe ne correspondent pas";
                    e.preventDefault();
                }
            }
        })
    </script>





    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>

    <script type="text/javascript">
        $(function() {
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>


    <script type="text/javascript">
        $(function() {
            // Les deux tableaux de devis : les devis en attente et l'historique
            // de ceux passés en commande. Cinq lignes par page — les tableaux
            // sont dans un onglet, une page longue y noierait le reste.
            //
            // Chacun n'est rendu que s'il contient des lignes : initialiser
            // DataTables sur un tableau vide provoque « Requested unknown
            // parameter ». D'où le test de présence avant chaque appel.
            ['#listeDevis', '#historiqueDevis'].forEach(function (selecteur) {
                var $t = $(selecteur);
                if (!$t.length || $t.find('tbody tr').length === 0) return;

                $t.DataTable({
                    language: {
                        url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                    },
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'Tout']],
                    order: [],
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                });
            });
        });
    </script>

    <script type="text/javascript">
        $(function() {
            var $table = $('#listeLivraison').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

    <script type="text/javascript">
        $(function() {
            var $table = $('#listePaiements').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

    <script type="text/javascript">
        $(function() {
            var $table = $('#listeLocation').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

    <script>
        function listePaiement(paye) {
            fetch('/liste-des-paiements-'+paye, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },

            })
            .then(response => response.json())
            .then(data => {
                // Afficher des messages dans la console si nécessaire ou traiter les données
                console.log(data);

                $('#tbody').html('');
                let checkbox = '';
                data.forEach(function(item) {
                    if (item.statut == 2) {
                        checkbox = `
                            <input type="checkbox" onclick="payer(${item.id})" value="${item.id}" class="form-check-input checkMontant"
                            name="paiements[]" id="${item.montant_total}">
                        `;
                    }

                    $('#tbody').append(`
                    <tr>
                        <td>
                            ${checkbox}
                        </td>
                        <td> ${item.code} </td>
                        <td> ${item.libelle} </td>
                        <td> ${formatNumber(item.montant_total)} fcfa </td>
                        <td> ${formatDate(item.created_at)} </td>

                    </tr>
                    `);
                });

            })

            .catch(error => console.error('Erreur de mise à jour de la localisation:', error));

        }

        function formatNumber(number) {
            return number.toLocaleString('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }
        function formatDate(dateString) {
            // Créer un objet Date à partir de la chaîne de caractères
            const date = new Date(dateString);

            // Extraire les composants de la date
            const day = String(date.getDate()).padStart(2, '0'); // Jour (avec un zéro devant si nécessaire)
            const month = String(date.getMonth() + 1).padStart(2, '0'); // Mois (les mois commencent à 0, donc +1)
            const year = date.getFullYear(); // Année
            const hours = String(date.getHours()).padStart(2, '0'); // Heures
            const minutes = String(date.getMinutes()).padStart(2, '0'); // Minutes

            // Retourner la date formatée
            return `${day}-${month}-${year} à ${hours}:${minutes}`;
        }

        function payer(id) {


            let bouton = document.getElementById('button-payer');
            // let button = document.getElementById('bu-payer');

            console.log(id)

            let checkboxes = document.querySelectorAll('.checkMontant:checked');

            console.log(checkboxes)
            let total = 0;

            checkboxes.forEach(function(checkbox) {
                total += parseFloat(checkbox.id);
            });

            if(total <= 0 ){
            bouton.style.display = 'none';
            }else{
                bouton.style.display = 'block';
            }

            document.getElementById('span-payer').textContent = formatNumber(total)+' fcfa';
            // Afficher des messages dans la console si nécessaire ou traiter les données


        }
    </script>

@endsection

@endsection
