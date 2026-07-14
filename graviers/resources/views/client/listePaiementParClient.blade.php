@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title','Liste des commandes')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des paiements de : {{ucwords($paiements->first()->client->nom.' '.$paiements->first()->client->prenom)}} </h2>

        </div>
    </div>

        @if(session('success'))
            <div class="alert alert-success text-center">
                {{session('success')}}
            </div>
        @endif

    <div class="row">
        <div class="col-md-10">
            <span class="fw-bold bg-primary text-white" style="border-radius: 5px; padding: 2px 5px">Veuillez selectionner les paiements à effectuer</span>
            <div class="card mb-4">
                <form action="{{route('client.afficherMontant')}}" method="post">
                    @csrf
                    <button type="submit" id="paiement" style="display:none"></button>
                    <!-- card-header end// -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered tablee">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="background-color: #1c57a3; color: white"></th>
                                        <th class="text-start" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">code</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Libelle</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Créé le</th>
                                        <!-- <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Paiement</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Réduction</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Paiement</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Enlevement</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th> -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <form action="">
                                        @foreach ($paiements as $paiement)
                                            @if($paiement->statut == 2)
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="checkbox" onclick="payer({{$paiement->id}})" value="{{$paiement->id}}" class="form-check-input"
                                                        name="paiements[]" id="{{$paiement->montant_total}}">
                                                    </td>
                                                    <td class="text-start">{{$paiement->code}}</td>
                                                    <td class="text-center">{{$paiement->libelle}}</td>
                                                    <td class="text-center">{{number_format($paiement->montant_total,'0','',' ')}}fcfa</td>
                                                    <td class="text-center">{{($paiement->created_at)->format('d-m-Y à H:i')}}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </form>


                                </tbody>
                            </table>
                        </div>
                        <!-- table-responsive //end -->
                    </div>
                    <!-- card-body end// -->
                </form>
            </div>
            <!-- card end// -->
        </div>
        <div class="col-md-2">
            <label for="paiement" class="btn btn-success" id="button-payer" style="display: none">Payer <br> <span class="fw-bold" id="span-payer">10 000 fcfa</span></label>
        </div>
    </div>
    <div class="pagination-area mt-15 mb-50">

    </div>

@endsection
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.tablee').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>
    <script>

function formatNumber(number) {
    return number.toLocaleString('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

function payer(id) {


    let bouton = document.getElementById('button-payer');
    // let button = document.getElementById('bu-payer');

    console.log(id)

    let checkboxes = document.querySelectorAll('.form-check-input:checked');

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
