    @php
        $typeUser = Auth::user()->type_user_id;
        // dd($typeUser)
    @endphp

<nav>
    <div class="col-12 bg-primary text-center text-white fw-bold p-3" >

            {{ucfirst(Auth::user()->type_user->nom)}}


    </div>
                <ul class="menu-aside">


                    {{-- navbar gestionnaire --}}
                    @if ($typeUser == 3 || $typeUser == 2)
                        @include('layout.navGestionnnaire')
                    @endif

                        {{-- navbar fournisseur --}}
                    @if ($typeUser == 5)
                        @include('layout.navFournisseur')
                    @endif

                    @if ($typeUser == 8)
                        {{-- STOCK --}}
                        @include('layout.navLivreur')
                    @endif

                    {{-- navbar agent SAV --}}
                    @if ($typeUser == 7)
                        @include('layout.navAgent')
                    @endif


                        {{-- navbar apporteur d'affaire --}}

                        @if ($typeUser == 6)
                            {{-- STOCK --}}
                            @include('layout.navApporteur')
                        @endif
                </ul>
                <hr />

                <br />
                <br />
            </nav>

