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
                {{-- PLEINE LARGEUR (09/09/2026) : les deux tableaux tenaient dans des
                     colonnes d'un tiers, coupées et à faire défiler ; ils occupent
                     désormais toute la largeur, l'un sous l'autre, avec leurs boutons
                     d'export, et le formulaire de validation vient en dessous. --}}
                <div class="row mt-5">
                    <div class="col-12 mb-4">
                        <h5>Détail Produit</h5>
                        <x-export-buttons table-id="tableProduitBon"
                                          filename="bon-{{ $bon->code_enleve }}-produit"
                                          title="Bon {{ $bon->code_enleve }} — produit" />
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tableProduitBon">
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

                                            {{ $bon->produit?->nom }}

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
                    <div class="col-12 mb-4">

                        <h5>Détail livreur</h5>
                        <x-export-buttons table-id="tableLivreurBon"
                                          filename="bon-{{ $bon->code_enleve }}-livreur"
                                          title="Bon {{ $bon->code_enleve }} — livreur" />
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tableLivreurBon">
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
                                                    @if ($bon->livraison?->livre_par == 1)
                                                        {{ $bon->livraison?->livreur?->user?->nom_prenoms ?? '—' }}
                                                    @else
                                                        {{ $bon->livraison?->clientLivreur?->nom ?? '—' }}
                                                    @endif
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            @if ($bon->livraison?->livre_par == 1)
                                                {{ $bon->livraison?->livreur?->user?->contact ?? '—' }}
                                            @else
                                                {{ $bon->livraison?->clientLivreur?->contact ?? '—' }}
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-12">

                        @if ($bon->fournisseur_validation == null)
                            {{-- LA QUANTITÉ SERVIE SE SAISIT DANS UNE FENÊTRE (10/09/2026).
                                 Le champ et le bouton traînaient sous les tableaux ; ils
                                 sont dans une fenêtre du même dessin que « Dépôt d'une
                                 avance », ouverte par un seul bouton. La confirmation
                                 SweetAlert2 avant l'envoi est conservée. --}}
                            <div class="d-flex justify-content-end mt-2">
                                <button type="button" class="btn btn-success rounded font-sm" id="ouvrir-servir-bon"
                                        data-bs-toggle="modal" data-bs-target="#modalServirBon">
                                    <i class="material-icons md-inventory"></i> Servir ce bon
                                </button>
                            </div>

                        @endif

                        {{-- <div class="col-8"></div> --}}

                    </div>

                </div>
            @endif
        </div>
    </div>
    <div class="card">
    </div>

    {{-- La fenêtre vit HORS de la carte : le thème pose un transform sur
         .card:hover, qui capture une fenêtre fixe placée dedans (elle restait
         sous le voile, grise et décalée). Même rangement que la fenêtre
         « Dépôt d'une avance ». --}}
    @if ($bon && $bon->fournisseur_validation == null)
        <div class="modal fade" id="modalServirBon" tabindex="-1" aria-labelledby="modalServirBonLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="validation-bon-form" action="{{ route('sellers.validate', $bon->code_enleve) }}" method="post">
                        @csrf
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="modalServirBonLabel">
                                <i class="material-icons md-inventory"></i> Quantité servie — bon {{ $bon->code_enleve }}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-muted small">Bon d'enlèvement</div>
                                    <strong class="text-dark">{{ $bon->code_enleve }}</strong>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small">Quantité à récupérer</div>
                                    <strong class="text-dark">{{ $bon->qte }}</strong>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="qteServiInput">Quantité servie <span class="text-danger">*</span></label>
                                    <input placeholder="Veuillez entrer la quantité servie" required type="number"
                                        min="0.1" step="any" max="{{ $bon->qte }}" name="qteServi"
                                        class="form-control" id="qteServiInput" autocomplete="off">
                                    <small class="text-muted">Au plus {{ $bon->qte }}, la quantité du bon.</small>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-success" type="button" id="open-confirmation-bon">
                                <i class="material-icons md-check"></i> Valider la quantité servie
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
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

            $('#modalServirBon').on('shown.bs.modal', function () {
                $qteInput.trigger('focus');
            });

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
