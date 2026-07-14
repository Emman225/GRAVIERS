@extends('layout.main')
@section('contenu')
@section('title','Modification de produit')

{{-- @dd($stock); --}}

    <div class="card mx-auto "style=" width: 40rem; ">
        <div class="card-body">
            @if (session('succes'))
                <div class="alert alert-success">
                    {{ session('succes') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif
                    <h4 class="card-title mb-4">Informations du {{$produits->nom}}</h4>
            <form action="" method="post" >
                @csrf
                    <div class="mb-3">
                        <label class="form-label">prix : <span class="text-danger">*</span></label>
                        <input class="form-control" value="{{$stock->prix}}" name="prix" type="number" />
                        <span class="text-danger">
                            @error('prix')
                                {{$message}}
                            @enderror
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"> Quantité :  <span class="text-danger">*</span></label>
                        <input class="form-control" value="{{$stock->qte}}" name="quantite"  type="number" />
                        <span class="text-danger">
                            @error('quantite')
                                {{$message}}
                            @enderror
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"> Me signaler si la quantité est en dessous de :  <span class="text-danger">*</span></label>
                        <input class="form-control" value="{{$stock->seuil_alert}}" name="seuil"  type="number" />
                        <span class="text-danger">
                            @error('seuil')
                                {{$message}}
                            @enderror
                        </span>
                    </div>


                <!-- form-group  .// -->
                <div class="mb-4">
                    <a href="{{route('sellers.stock')}}" class="btn btn-primary">Retour</a>
                    <button type="submit" class="btn btn-success">Modifier</button>
                </div>
                <!-- form-group// -->
            </form>


            {{-- <p class="text-center mb-2">Already have an account? <a href="{{route('show.login')}}">Sign in now</a></p> --}}
        </div>
    </div>
@endsection

@section('jsParts')
    <script type="text/javascript">
        $(function() {
            $('#produits').select();
        });
    </script>
@endsection
