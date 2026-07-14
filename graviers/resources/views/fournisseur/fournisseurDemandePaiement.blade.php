@extends('layout.main')

@section('contenu')
@section('title','Demande de paiement')

            @if (session('error'))
                <div class="alert alert-danger text-center" id="notify">
                    {{ session('error') }}
                </div>
            @endif
            @if (session('success'))
                <div class="alert alert-success text-center" id="notify">
                    {{ session('success') }}
                </div>
            @endif

<div class="card mt-200 mx-auto" style="margin: auto; width: 30rem; height: 20rem">


    <div class="card-body" style="">
        <h4 class="card-title mb-4 text-center ">
            Entrez le montant souhaité ! <br>

            <p class="bg-success text-white m-2">Solde: {{number_format($frs->solde,0,'',' ')}} fcfa </p>

        <form method="post" id="form" action="">
            @csrf

            <div class="mb-3">
                <input class="form-control" name="montant" required id="montant" placeholder="Montant en fcfa" type="number" />
                <small class="text-danger" id="error"></small>
            </div>
            <div class="mb-3">
                <input class="form-control" name="numero" required id="montant" placeholder="Numero de compte" type="number" />
                <small class="text-danger" id="error"></small>
            </div>
            <div class="mb-3">
                <select class="form-select"  name="modePaie" id="modePaie">
                    <option value="">Selectionnez le mode de paiement</option>
                    @foreach ( $modesPaie as $mode)
                        <option value="{{ $mode->id }}">{{$mode->libelle}}</option>
                    @endforeach
                </select>
                <small class="text-danger" id="error"></small>
            </div>
            <div class="mb-4">
                <button type="submit" class="text-center btn btn-primary w-100">Demander le paiement</button>
            </div>
            <div class="mb-3">

            </div>

        </form>

    </div>
</div>
@endsection

@section('jsParts')

    {{-- <script>

        let montant = document.getElementById('montant');
        let form = document.getElementById('form')
        let error = document.getElementById('error')

        form.addEventListener('submit', function(e){
            e.preventDefault();
            // alert(montant.value)
            if(montant.value < 1 ){
                error.innerHTML = "Veuillez entrer un montant supérieur à 0"

            setTimeout(() => {
                error.innerHTML = ""
            }, 3000);

            }
        })



    </script> --}}


@endsection
