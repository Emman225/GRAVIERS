{{-- @dd($vehicules) --}}

@extends('layout.main')
@section('contenu')
@section('title', 'Modification de produit')


{{-- @dd($vehicules) --}}
<div class="container mt-60">
    <h1>Demandes de livraison</h1>
    @foreach ($livraisons->detailLivraison as $detail )
        @if ($detail->livraisons->sum('qte') == $detail->qte)
            <span class="text-white col-12 text-center bg-success h4">
                {{ucfirst($detail->nom_produit).': Déjà traité '}}
            </span><br><br>
        @else

        <h5 class="text-danger"> {{ucfirst($detail->nom_produit)}} </h5>
            <form action="{{route('show.traitementLivraison',['demandeLivraison' => $livraisons, 'detail' => $detail])}}" method="post" class="d-flex">
                @csrf
                <div class="card mx-auto me-5" style=" width: 40rem; ">
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success" id="notify">
                                {{ session('success') }}
                            </div>
                        @endif
                        <div>
                            <h4 class="card-title mb-4">Quantité restant: <span
                                    id="qte{{$detail->id}}">{{ Help::qteDetaillivraisonRestante($detail) }}</span>
                            </h4>
                        </div>

                            <div class="mb-3">
                                <label class="form-label">Date de livraison : <span class="text-danger">*</span></label>
                                <input class="form-control"  name="date" type="date" />
                                <span class="text-danger">
                                    @error('prix')
                                        {{ $message }}
                                    @enderror
                                </span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Vehicule : <span class="text-danger">*</span></label>
                                <select class="form-control" name="matricule" multiple style="height: 150px" id="">
                                    {{-- <option value="">Selectionner un vehicule</option> --}}
                                    @foreach ($vehicules as $vehicule)
                                    @if ($vehicule->disponible == true )
                                        <option onclick="vehiculeSelected({{ $vehicule->id }},{{$detail->id}})" value="{{ $vehicule->matricule }}">
                                            {{ $vehicule->marque .
                                                ' | ' .
                                                $vehicule->capacite .
                                                ' | ' .
                                                $vehicule->livreur->user->nom_prenoms .
                                                ' | ' .
                                                $vehicule->livreur->user->contact }}
                                        </option>
                                    @endif
                                    @endforeach


                                </select>
                                <span class="text-danger">
                                    @error('prix')
                                        {{ $message }}
                                    @enderror
                                </span>
                            </div>

                            {{-- <div class="mb-4">
                                <button type="submit" class="btn btn-primary">Valider</button>
                            </div> --}}

                            <!-- form-group// -->
                            <div class="erreur text-danger text-center fw-bold" id="error{{$detail->id}}" ></div>
                    </div>
                </div>
                <div class="card mx-auto ms-5" style=" width: 40rem; ">
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success" id="notify">
                                {{ session('success') }}
                            </div>
                        @endif

                        <h4 class="card-title mb-4">Véhicule sélectionnés </h4>

                        <div class="table table-striped">
                            <table class="table table-striped" id="table">
                                <thead>
                                    <th class="text-center">marque</th>
                                    <th class="text-center">Capacité</th>
                                    <th class="text-center">Matricule</th>
                                    <th class="text-center">Action</th>
                                </thead>
                                <tbody id="listCar{{$detail->id}}">


                                </tbody>
                            </table>
                        </div>
                        <div class="container">
                            <button type="submit" id="button{{$detail->id}}" disabled class="btn btn-primary"> Valider </button>
                        </div>


                    </div>
                </div>
            </form>
        @endif

    @endforeach
</div>
@endsection

@section('jsParts')
<script type="text/javascript">

function supprimerUneLigne(capacite,detail,vehicule,qteEnleve){

    // console.log(capacite,detail,immatriculation)


    console.log(parseInt($('#qte'+detail).text())+capacite)

    let lesInputs = document.getElementById('listCar'+detail)

    let lignes = lesInputs.getElementsByTagName('tr');

    for(let i = 0; i<lignes.length; i++){
    let lesCellules = lignes[i].getElementsByTagName('td')
        if(lesCellules[4].textContent == vehicule){
            lesInputs.deleteRow(i)
            // let qte = parseInt($('#qte'+detail).text())+(capacite);
            $('#qte'+detail).html(qteEnleve)
        }
    }

    // let lesInputs = document.getElementById('listCar'+detail)

    // let lignes = lesInputs.getElementsByTagName('tr');

    if(lignes.length == 0){
        $('#button'+detail).attr('disabled', true)
        console.log('cest le cas')
    }
    // let car = $('#id[]                                                                                                                                                                                                                                                                                                                                                                                                                                                   ').val()
    // console.log(matricule)

    //var nombreDeLignes = $('#table tbody tr').length;

    //var ligne = $(this).closest('tr');
    //if(nombreDeLignes == 1) return;

    // Supprimer la ligne
   // ligne.remove();:

}


</script>
@endsection
