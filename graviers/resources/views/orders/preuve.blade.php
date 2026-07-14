
@extends('layout.main')

@section('contenu')
@section('title','Preuve de virement')
    @if (session('saved'))
        <div class="alert alert-success text-center">
            {{ session('saved') }}
        </div>
    @endif
    @php
// recuperation des produits associés au fournisseur
// $produitSelectionne = $fournisseur->produits()->pluck('produit_id');

@endphp
@if ($commande->preuve->statut == 1)
    <div class="alert alert-success text-center">
        Cette preuve a été validée par {{$commande->preuve->user->nom_prenoms}}
    </div>
@endif
{{-- @dd($fournisseur->user) --}}
    <div class=" mt-20 card mx-auto "style=" width: 50rem; ">
        <div class="card-body">

            <h4 class="card-title mb-4">Preuve de virement de la commande N°{{$commande->numero}} </h4>
            <form action="" method="post" >
                @csrf
                <div class="mb-3">
                    <a class="btn btn-info" download href="{{asset('storage/'.$commande->preuve->fichier)}}">Télécharger le reçu de virement</a>
                </div>
                <div class="mb-3">
                    <label class="form-label"><b class="fw-bold">Rérérence</b></label>
                    <input class="form-control" disabled readonly value="{{$commande->preuve->reference}}" type="text" />
                    <span class="text-danger">
                        @error('nom_prenoms')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label"><b class="fw-bold">Numéro de compte</b></label>
                    <input class="form-control" disabled readonly value="{{$commande->preuve->num_compte}}" type="text" />
                    <span class="text-danger">
                        @error('nom_prenoms')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label"><b class="fw-bold">Banque</b></label>
                    <input class="form-control" disabled readonly value="{{$commande->preuve->banque}}" type="text" />
                    <span class="text-danger">
                        @error('nom_prenoms')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label"><b class="fw-bold">Date de l'opération bancaire </b></label>
                    <input class="form-control" disabled readonly value="{{$commande->preuve->date_operation}}" type="text" />
                    <span class="text-danger">
                        @error('nom_prenoms')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label"><b class="fw-bold">Note supplémentaire</b></label>
                    <textarea name="" disabled readonly id="" cols="30" rows="10" class="form-control"> {{$commande->preuve->statut}} </textarea>
                    <span class="text-danger">
                        @error('nom_prenoms')
                            {{$message}}
                        @enderror
                    </span>
                </div>

                @if ($commande->preuve->statut == 2)
                    <div class="mb-3">
                    <button type="submit"  class="btn btn-success">Je valide les informations</button>
                    </div>
                @endif





            </form>
        </div>
    </div>
@section('jsParts')
    <script type="text/javascript">
        $(function() {
            $('#produits').select();
        });
    </script>

@endsection

@endsection
