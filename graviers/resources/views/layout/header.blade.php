{{-- @dd(Auth::user()); --}}
@auth
    @php
        $infoUser = Auth::user() ?? new stdClass();
        // dd($infoUser)
    @endphp
@endauth

<header class="main-header navbar">
    @guest
    <a  class="brand-wrap">
        <img src="{{ asset(config("constantes.logo")) }}" class="logo" alt="Nest Dashboard" />


    </a>
    <li class="nav-item">
        <a class="nav-link btn-icon" href="{{route('client.panier')}}">
            <i class="material-icons md-shopping-cart animation-shake"></i>
            <span class="material-symbols-outlined">
                shopping_cart
            </span>
            <span class="badge rounded-pill"> {{Cart::count()}} </span>
        </a>
    </li>
    @endguest

    <div class="col-search">
        <form class="searchform">

            <datalist id="search_terms">
                <option value="Products"></option>
                <option value="New orders"></option>
                <option value="Apple iphone"></option>
                <option value="Ahmed Hassan"></option>
            </datalist>
        </form>
    </div>
    @auth
        <form action="{{ route('show.logout') }}" method="post">
            @method('delete')
            @csrf
            <div class="col-nav">
                <button class="btn btn-icon btn-mobile me-auto" data-trigger="#offcanvas_aside"><i
                        class="material-icons md-apps"></i></button>
                <ul class="nav">
                    {{-- <li class="nav-item">
                        <a class="nav-link btn-icon" href="">
                            <span class="material-symbols-outlined">
                                shopping_cart
                            </span>
                            <span class="badge rounded-pill">
                                {{Cart::count()}}

                            </span>
                        </a>
                    </li> --}}
                    <li class="nav-item">
                        <a class="nav-link btn-icon darkmode" href="#"> <i class="material-icons md-nights_stay"></i>
                        </a>
                    </li>


                    <li class="nav-item">
                        <a href="#" class="requestfullscreen nav-link btn-icon"><i
                                class="material-icons md-cast"></i></a>
                    </li>

                    @php
                        $displayName = $infoUser->nom_prenoms ?? $infoUser->login ?? 'Utilisateur';
                        $userInitials = strtoupper(
                            mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $displayName) ?: 'U', 0, 1)
                        );
                        // Couleur déterministe basée sur le nom
                        $avatarPalette = ['#1c57a3', '#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#ef4444'];
                        $avatarColor = $avatarPalette[crc32($displayName) % count($avatarPalette)];
                    @endphp
                    @php
                        // Résolution robuste du chemin de la photo :
                        //  - Si la valeur en DB contient déjà un dossier (ex: "imageUser/x.jpg")
                        //    on l'utilise tel quel.
                        //  - Sinon on suppose le dossier 'imageUser/' (convention des uploads
                        //    via registerAdmin / storeUser / gestionnaires).
                        //  - On vérifie l'existence physique avant de servir le fichier.
                        $photoPath = null;
                        if ($infoUser->photo) {
                            $candidate = str_contains($infoUser->photo, '/')
                                ? $infoUser->photo
                                : 'imageUser/' . $infoUser->photo;
                            if (file_exists(public_path('storage/' . $candidate))) {
                                $photoPath = $candidate;
                            }
                        }
                        $photoExists = $photoPath !== null;

                        // URL de la page paramètre selon le type d'utilisateur
                        $parametreUrl = match((int) $infoUser->type_user_id) {
                            \Help::$USER_SA, \Help::$USER_ADMIN, \Help::$USER_GESTIONNAIRE => route('show.parametre'),
                            \Help::$USER_FOURNISSEUR  => route('sellers.parametreFournisseur'),
                            \Help::$USER_APPORTEUR    => route('apporteur.parametreApporteur'),
                            \Help::$USER_LIVREUR      => route('livreur.parametreLivreur'),
                            default                   => route('enConstruction'),
                        };
                    @endphp
                    <li class="dropdown nav-item">
                        <a class="dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" data-bs-offset="-16,12"
                            href="#" id="dropdownAccount" aria-expanded="false">
                            @if ($photoExists)
                                <img class="img-xs rounded-circle" src="{{ asset('storage/' . $photoPath) }}"
                                     alt="{{ $displayName }}"
                                     style="width:36px;height:36px;object-fit:cover;"
                                     onerror="this.outerHTML='<span class=&quot;header-avatar&quot; style=&quot;background:{{ $avatarColor }}&quot;>{{ $userInitials }}</span>'" />
                            @else
                                <span class="header-avatar" style="background: {{ $avatarColor }}">{{ $userInitials }}</span>
                            @endif
                            <span class="header-user-name">{{ $infoUser->login }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownAccount">
                            <a class="dropdown-item" href="{{ route('show.monProfil') }}"><i class="material-icons md-perm_identity"></i>{{ $infoUser->login }}</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ $parametreUrl }}"><i class="material-icons md-settings"></i>Paramètre du compte</a>
                            <a class="dropdown-item" href="{{ route('show.centreAide') }}"><i class="material-icons md-help_outline"></i>Centre d'aide</a>
                            <div class="dropdown-divider"></div>
                            <button class="dropdown-item text-danger" type="submit"><i class="material-icons md-exit_to_app"></i>Déconnexion</button>
                        </div>
                    </li>
                </ul>
            </div>
        </form>
    @endauth
    @guest
        <li class="nav-item">
            <a class="nav-link btn-icon btn btn-primary text-white" href="{{ route('client.login') }}"> Se connecter </a>
        </li>
        <li class="nav-item">
            <a class="nav-link btn-icon btn btn-primary text-white" href="{{ route('client.register') }}"> Créer un compte </a>
        </li>
    @endguest

</header>
{{-- Le bandeau "stock insuffisant" (session finDeStock) a été déplacé dans
     layout/main.blade.php, à l'intérieur de .content-main, pour ne plus passer
     sous le header fixe (72px) qui coupait son haut. --}}

