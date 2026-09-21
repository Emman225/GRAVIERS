@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title','Création de code promo')
@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title">Créez un code promo</h2>
                        <p>Gerez facilement vos codes promo</p>
                    </div>
                    {{-- Ce bloc portait un champ « Search Categories » qui ne
                         cherchait rien : il n'était relié à aucun code. Le
                         tableau a sa propre recherche. --}}
                    <div>
                        @if (request()->boolean('nouveau') || ($leCode->id ?? null))
                            <a href="{{ route('show.creationDeCodePromo') }}" class="btn btn-sm btn-outline-secondary"><i class="material-icons md-arrow_back align-middle"></i> Retour à la liste</a>
                        @else
                            <a href="{{ route('show.creationDeCodePromo') }}?nouveau=1" class="btn btn-sm btn-primary"><i class="material-icons md-add align-middle"></i> Nouveau code promo</a>
                        @endif
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        @php
                            $modeFormulaire = request()->boolean('nouveau') || ($leCode->id ?? null);
                        @endphp
                        <div class="row">
                            @if ($modeFormulaire)
                            <div class="col-12 mb-4">
                                <form method="post" action="" enctype="multipart/form-data">

                                    @csrf
                                    <div class="mb-4">
                                        @if(session('succes'))
                                            <div class="alert alert-success">
                                                {{session('succes')}}
                                            </div>
                                        @endif
                                        <label for="product_name" class="form-label">Libelle</label>
                                        <input type="text"  value="{{$leCode->libelle}} " name="libelle" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('libelle')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label for="product_name" class="form-label">Date de debut du code promo</label>
                                        <input type="date"  value="" name="debut" value="{{Carbon::parse($leCode->debut)->format('d-m-Y')}}" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('parent')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label for="product_name" class="form-label">Date de fin du code promo</label>
                                        <input type="date"  value="" name="fin" value="{{$leCode->fin}}" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('parent')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Taux de réduction</label>
                                        <input type="number" required class="form-control" value="{{$leCode->taux_reduction}}" name="taux" id="product_name" />
                                        <span class="text-danger">
                                            @error('image')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    {{-- <div class="mb-4">
                                        <label class="form-label">Choisissez une icon <small class="text-mutted fw-bold">(Facultatif)</small> </label>
                                        <input type="file"  class="form-control" name="icon" id="product_name" />
                                        <span class="text-danger">
                                            @error('icon')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div> --}}


                                    <div class="d-grid">
                                        @if ($leCode->id)
                                            <button type="submit" formaction="{{route('show.codeUpdated',$leCode)}}" class="btn btn-primary">Enregistrer</button>
                                            <a href="{{route('show.creationDeCodePromo')}}">Créer un nouveau code</a>
                                        @else
                                            <button type="submit" formaction="{{route('show.enregistrementDeCodePromo')}}" class="btn btn-primary">Générer un code promo</button>
                                        @endif
                                    </div>
                                </form>
                            </div>
                            @endif
                            <div class="col-12">
                                <x-export-buttons table-id="listeCodesPromo"
                                                  filename="liste-des-codes-promo"
                                                  title="Liste des codes promo" />
                                <div class="table-responsive">
                                    <table class="table table-striped" id="listeCodesPromo">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Libelle</th>
                                                <th>Code</th>
                                                <th>Taux de réduction</th>
                                                <th>Début</th>
                                                <th>Fin</th>
                                                <th>Validité</th>
                                                <th>Créé par</th>
                                                <th class="text-end">Action</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($reductions as $reduction)


                                            <tr>
                                                <td>{{$reduction->id}}</td>
                                                <td>{{$reduction->libelle}}</td>
                                                <td class="text-nowrap">
                                                    <b>{{$reduction->code}}</b>
                                                    {{-- Copier et partager le code (09/09/2026), comme les codes de livraison côté client. --}}
                                                    @php
                                                        $texteCode = "Code promo GRAVIER.COM : {$reduction->code} — {$reduction->taux_reduction} % de réduction, valable du "
                                                            . \Help::dateHeure($reduction->debut) . ' au ' . \Help::dateHeure($reduction->fin) . '.';
                                                    @endphp
                                                    <button type="button" class="btn btn-sm btn-light js-copier-code ms-1" data-code="{{ $reduction->code }}" title="Copier le code">
                                                        <i class="material-icons md-content_copy"></i>
                                                    </button>
                                                    <a class="btn btn-sm btn-success" target="_blank" rel="noopener" title="Partager par WhatsApp"
                                                       href="https://wa.me/?text={{ rawurlencode($texteCode) }}">
                                                        <i class="fa-brands fa-whatsapp"></i>
                                                    </a>
                                                </td>
                                                <td> {{$reduction->taux_reduction}}% </td>
                                                <td> {{Carbon::parse($reduction->debut)->format('d-m-Y')}} </td>
                                                <td> {{Carbon::parse($reduction->fin)->format('d-m-Y')}}</td>
                                                <td>
                                                    @if($reduction->est_utilise == 1)
                                                        <span class="badge bg-danger">Code invalide</span>
                                                    @elseif($reduction->est_expire)
                                                        <span class="badge bg-secondary">Expiré</span>
                                                    @else
                                                        <span class="badge bg-success">Code valide</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $reduction->user?->nom_prenoms }}
                                                </td>
                                                {{-- <td> {{$list->parent_id}} </td> --}}
                                                <td class="text-nowrap text-end">
                                                    <div class="dropdown">
                                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm" title="Actions"> <i class="material-icons md-more_horiz"></i> </a>
                                                        <div class="dropdown-menu">

                                                            <a class="dropdown-item" href="{{route('show.updateDeCodePromo',$reduction)}}">Modifier les informations</a>
                                                            <a class="dropdown-item text-danger" href="{{route('show.suppressionDeCodePromo',$reduction)}}">Supprimer</a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- .col// -->
                        </div>
                        <!-- .row // -->
                    </div>
                    <!-- card body .// -->
                </div>
                <!-- card .// -->

    @endsection
    @section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script>
        // Copier le code promo (09/09/2026) : presse-papiers, repli par sélection.
        document.addEventListener('click', function (e) {
            var b = e.target.closest('.js-copier-code');
            if (!b) return;
            var code = b.getAttribute('data-code') || '';
            var fini = function () {
                var i = b.querySelector('i'); if (!i) return;
                var avant = i.className; i.className = 'material-icons md-check'; b.classList.add('btn-success');
                setTimeout(function () { i.className = avant; b.classList.remove('btn-success'); }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(fini, function () { window.prompt('Copiez le code :', code); });
            } else {
                window.prompt('Copiez le code :', code);
            }
        });
    </script>
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
