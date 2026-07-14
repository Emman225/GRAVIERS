@extends('layout.main')

@section('contenu')
@section('title', 'Liste des Commissions')



{{-- @dd($fournisseur) --}}




<section class="content-main">
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des commissions des apporteurs d'affaires</h2>

        </div>

        {{-- <div>
                        <a href="#" class="btn btn-light rounded font-md">Export</a>
                        <a href="#" class="btn btn-light rounded font-md">Import</a>
                        <a href="{{ route('sellers.add') }}" class="btn btn-primary btn-sm rounded">Enregistrer un nouveau fournisseur</a>
                    </div> --}}
    </div>
    <div class="card mb-4">
        <form method="GET" action="{{ route('show.commissions') }}" class="row">
            @csrf
            <div class="col-md-3 col-lg-3 col-xl-3">
                <select class="form-control" name="apporteur" id="apporteur">
                    <option value="0" disabled>Choisir l'apporteur d'affaire</option>
                    @foreach ($apporteurs as $a)
                        <option @selected(Request::get('apporteur') == $a->id) value="{{ $a->id }}">{{ $a->nom_prenoms }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-3 col-xl-3">
                <select class="form-control" name="type_affaire" id="type_affaire">
                    <option value="" disabled>Choisir le type de commission</option>
                    <option value="COMMANDE" @selected(Request::get('apporteur') == "COMMANDE")>COMMANDE</option>
                    <option value="LOCATION" @selected(Request::get('apporteur') == "LOCATION")>LOCATION</option>
                    <option value="LIVRAISON" @selected(Request::get('apporteur') == "LIVRAISON")>LIVRAISON</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-2 col-xl-2">
                <button type="submit" class="btn btn-primary ">Rechercher</button>

            </div>
        </form>
        <!-- card-header end// -->
        <div class="card-body">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th class="text-center" style="background-color: #1c57a3; color: white">Code</th>
                        <th class="text-center" style="background-color: #1c57a3; color: white">Nom & Prénoms</th>
                        <th class="text-center" style="background-color: #1c57a3; color: white">Commission</th>
                        <th class="text-center" style="background-color: #1c57a3; color: white">Type Affaire</th>
                        <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                    </tr>
                </thead>
                <tbody>


                    @foreach ($coms as $c)
                        <tr>
                            <td class="text-center"> {{ $c->code }} </td>
                            <td class="text-center"> {{ $c->nom_prenoms }} </td>
                            <td class="text-center"> {{ Help::formatNombre($c->montant, true) }}</td>
                            <td class="text-center"> {{ $c->type_affaire }} </td>
                            <td class="text-center"> {{ $c->created_at->format('d-m-Y H:i') }} </td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
            {{ $coms->links() }}



        </div>
        <!-- card-body end// -->
    </div>
    <!-- card end// -->

</section>
<!-- content-main end// -->

@endsection
@section('jsParts')
<script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
<script type="text/javascript">
    $(function() {
        //Gestion du datatables
        $('.table').DataTable(order: [
            [3, 'desc']
        ]);
    });
</script>
@endsection
