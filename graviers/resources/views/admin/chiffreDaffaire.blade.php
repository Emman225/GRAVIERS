@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Chiffre d\'affaire par famille')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Chiffre d'affaire par famille - </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div> --}}
    </div>
    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div style="width:100%" class="col-lg-4 col-md-6 me-auto">
                    <p class="d-flex justify-content-between" >
                        <span class="text-success h4" >T. Qté : {{number_format($qteTotal,0,'','.')}} </span>
                        <span class="text-success h4" >T. Qté vendue : {{number_format($enlevements->sum('qte'),0,'','.')}}</span>
                        <span class="text-success h4" >T. Montant HT : {{number_format($totalMontant,0,'','.')}}fcfa
                        </span>
                        <span class="text-success h4" >T. TVA : 0%</span>
                        <span class="text-success h4" >T. Montant TTC : {{number_format($totalMontant,0,'','.')}} fcfa</span>
                    </p>
                </div>
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead stylr>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Famille</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Désignation</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Quantité vendue</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Montant HT</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white;">TVA</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Montant TTC</th>


                        </tr>
                    </thead>
                    <tbody>
                        @foreach($produits as $produit)
                            <tr>
                                <td class="text-center" >
                                    <div class="info pl-3">
                                        <h6 class="mb-0 title">
                                            @foreach ($produit->categories as $categorie)

                                            {{$categorie->nom}}
                                            @endforeach
                                        </h6>
                                    </div>
                                </td>

                                <td class="text-center"> {{$produit->nom}} </td>

                                <td class="text-center">
                                    @php
                                        $qteProduit = 0;
                                        foreach($produit->fournisseurs as $frs){
                                            $qteProduit += $frs->pivot->qte;
                                        }
                                    @endphp
                                    {{number_format($qteProduit,0,'',' ')}}
                                </td>

                                <td class="text-center">
                                    @php
                                        $qteEnlevement = 0;
                                        foreach($produit->enlevements as $enlevement){
                                            $qteEnlevement += $enlevement->qte;
                                        }
                                    @endphp
                                    {{number_format($qteEnlevement,0,'',' ')}}
                                </td>
                                <td class="text-center">
                                    {{number_format($produit->prix_moyen*$qteEnlevement,0,'','.') }}fcfa
                                </td>
                                <td class="text-center">
                                    0%
                                </td>
                                <td class="text-center">{{number_format($produit->prix_moyen*$qteEnlevement,0,'','.') }}fcfa</td>
                            </tr>
                        @endforeach


                    </tbody>
                </table>
                <!-- table-responsive.// -->
            </div>
        </div>
        <!-- card-body end// -->
    </div>
    <!-- card end// -->

@endsection


@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });
    </script>
@endsection
