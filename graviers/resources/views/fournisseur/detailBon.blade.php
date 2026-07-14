@extends('layout.main')
@section('title', 'Détails de bon')
@section('contenu')

    @if ($bon)
        @if ($bon->fournisseur_validation != null)
            <div class="alert alert-success text-center ">
                Bon traité

            </div>
        @endif
    @endif
    <div class="notify"></div>
    <div class="card">
        <div class="content-header">
            <a href="javascript:history.back()"><i class="material-icons md-arrow_back"></i> </a>
        </div>
        <!-- card-header end// -->
        <div class="card-body">

            @if ($bon)
                <h1> Enlevement : {{ $bon->code_enleve }} </h1>
                <div class="row mt-5">
                    <div class="col-6 col-md-4" style="">
                        <h5>Détail Produit</h5>
                        <div class="table-responsive">
                            <table disabled class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-center" width="40%">Désignation</th>
                                        <th class="text-center" width="20%">Qté à récuperer</th>
                                        @if ($bon->fournisseur_validation != null)
                                            <th class="text-center" width="20%">Qté servi</th>
                                        @endif
                                        {{-- <th width="20%" class="text-end">Total</th> --}}
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="text-center">

                                            {{ $bon->produit->nom }}

                                        </td>
                                        {{-- <td class="fw-bold h5 text-center" > {{ $produit->prix }} fcfa</td> --}}
                                        <td> {{ $bon->qte }} </td>

                                        @if ($bon->fournisseur_validation != null)
                                            <td> {{ $bon->qte_servi }} </td>
                                        @endif
                                        {{-- <td class="text-end"><dd><b class="h4 text-success fw-bold "> {{ $produit->prix * $bon->qte }} fcfa </b> <br>

                                        </td> --}}
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                        <!-- table-responsive// -->

                    </div>
                    <div class="col-6 col-md-4 text-center" style="">

                        <h5>Détail livreur</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-center" width="40%">Nom prénom</th>
                                        <th class="text-center" width="20%">Contact</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="text-center">
                                            <a>
                                                <div class="info ">
                                                    @if ($bon->livraison->livre_par == 1)
                                                        {{ $bon->livraison->livreur->user->nom_prenoms }}
                                                    @else
                                                        {{ $bon->livraison->clientLivreur->nom }}
                                                    @endif
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            @if ($bon->livraison->livre_par == 1)
                                                {{ $bon->livraison->livreur->user->contact }}
                                            @else
                                                {{ $bon->livraison->clientLivreur->contact }}
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 text-center" style="">

                        @if ($bon->fournisseur_validation == null)
                            <form id="validation-bon-form" action="{{ route('sellers.validate', $bon->code_enleve) }}"
                                method="post">
                                @csrf
                                <div class="col-6">
                                    <input placeholder="Veuillez entrer la quantité servie" required type="number"
                                        min="0.1" step="any" max="{{ $bon->qte }}" name="qteServi"
                                        class="form-control" id="qteServiInput">
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-success rounded font-sm mt-5" type="button"
                                        id="open-confirmation-bon">
                                        Valider la quantité servie
                                    </button>
                                </div>
                            </form>

                        @endif

                        {{-- <div class="col-8"></div> --}}

                    </div>

                </div>
            @endif
        </div>
    </div>
    <div class="card">
    </div>
@endsection

@section('jsParts')
    <script>

        $(function() {
            var $form = $('#validation-bon-form');
            var $qteInput = $('#qteServiInput');
            var $confirmBtn = $('#open-confirmation-bon');

            if (!$form.length || !$qteInput.length || !$confirmBtn.length) {
                return;
            }

            $confirmBtn.on('click', function(e) {
                e.preventDefault();
                if (!$qteInput[0].checkValidity()) {

                    $qteInput[0].reportValidity();
                    return;
                }
                // alert('ok');
                var qte = $qteInput.val();
                var codeBon = '{{ $bon->code_enleve }}';

                Swal.fire({
                    title: 'Confirmer la validation ?',
                    html: '<div style="text-align:left">' +
                        '<p style="margin-bottom:8px;">Bon: <strong>' + codeBon + '</strong></p>' +
                        '<p style="margin:0;">Quantité servie: <strong>' + qte + '</strong></p>' +
                        '</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, confirmer',
                    cancelButtonText: 'Annuler',
                    reverseButtons: true,
                    focusCancel: true,
                    customClass: {
                        popup: 'rounded',
                        confirmButton: 'btn btn-success rounded font-sm mx-1',
                        cancelButton: 'btn btn-light rounded font-sm mx-1'
                    },
                    buttonsStyling: false
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $form.trigger('submit');
                    }
                });
            });
        });
    </script>
@endsection
