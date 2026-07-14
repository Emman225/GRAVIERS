@extends('client.main')
@section('title', 'Devenir un client à terme')
@section('content')
    <main class="main">
        @if (session('success'))
            <div class="alert alert-success text-center"  id="notify">
                {{session('success')}}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger text-center"  id="notify">
                {{session('error')}}
            </div>
        @endif

        {{-- @dd($client->DemandeCompteClientATerme) --}}

        @if ($client->DemandeCompteClientATerme)

            @if ($client->DemandeCompteClientATerme->approuve == 1)
                <div class="container mb-80 mt-50">

                    <div class="row">
                        <h3>
                            Vous êtes maintenant un client à termes.
                        </h3>
                        <p>Vous avez la possibilité d'effectuer des achats et de régler la facture de manière échelonée</p>
                        <a href="{{route('client.index')}}" class="btn btn-primary w-50" >Continuer les achats </a >
                    </div>
                </div>

            @else
                <div class="container mb-80 mt-50">
                    <div class="row">
                        <div class="col-lg-8 mb-40">
                            <h1 class="heading-2 mb-10">Votre demande est en cours de traitement...</h1>
                            <div class="d-flex justify-content-between">
                            </div>
                        </div>
                    </div>
                    {{-- <div class="row">
                        <div class="col-lg-7">
                            <div class="row mb-50">

                                <div class="col-lg-6">

                                </div>
                            </div>
                            <div class="row">
                                <h4 class="mb-30">Formulaire de demande</h4>
                                <form method="post" action="{{route('client.demandeClientATerme')}}">
                                    @csrf
                                    <div class="row shipping_calculator">
                                        <p>Devenez un client à terme afin d'avoir la possibilité de prendre des produits</p><br>
                                        <p>et de régler la facture de manière échelonée</p><br>

                                        <input required type="text" name="objet" class="form-control mb-5"  placeholder="Objet">
                                        <textarea required name="description" id="" cols="30" class="mt-5" placeholder="Décrivez en quelques mot vos raisons de vouloir être un client à terme" rows="10"></textarea>

                                        <button type="submit" class="btn btn-fill-out btn-block mt-30">Envoyer la demande</button>

                                    </div>
                                </form>
                            </div>
                        </div>
                    </div> --}}
                </div>

            @endif
        @else

        <div class="container mb-80 mt-50">
            <div class="row">
                <div class="col-lg-8 mb-40">
                    <h1 class="heading-2 mb-10">Demander à être un client à terme</h1>
                    <div class="d-flex justify-content-between">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-7">
                    <div class="row mb-50">

                        <div class="col-lg-6">

                        </div>
                    </div>
                    <div class="row">
                        <h4 class="mb-30">Formulaire de demande</h4>
                        <form method="post" action="{{route('client.demandeClientATerme')}}" enctype="multipart/form-data">
                            @csrf
                            <div class="row shipping_calculator">
                                <p>Devenez un client à terme afin d'avoir la possibilité de prendre des produits</p><br>
                                <p>et de règler la facture de manière échélonnée</p><br>

                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul style="margin:0; padding-left:18px;">
                                            @foreach ($errors->all() as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="form-group mb-3">
                                    <label class="form-label">Objet <span class="text-danger">*</span></label>
                                    <input required type="text" name="objet" maxlength="255" class="form-control" value="{{ old('objet') }}" placeholder="Objet de la demande (max 255 caractères)">
                                    <small class="text-muted">Titre court de votre demande.</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Description / Justification <span class="text-danger">*</span></label>
                                    <textarea required name="description" cols="30" rows="6" class="form-control" placeholder="Décrivez en quelques mots vos raisons de vouloir être un client à terme">{{ old('description') }}</textarea>
                                </div>

                                <hr>
                                <h5 class="mb-2">Documents justificatifs</h5>
                                <p class="text-muted small">Joignez les pièces qui appuient votre demande (PDF / JPG / PNG / DOC — 5 Mo max par fichier).</p>

                                <div class="form-group mb-2">
                                    <label class="form-label">RCCM / Registre de commerce</label>
                                    <input type="file" name="documents[rccm]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                </div>
                                <div class="form-group mb-2">
                                    <label class="form-label">Attestation de revenus / bilan</label>
                                    <input type="file" name="documents[bilan]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                </div>
                                <div class="form-group mb-2">
                                    <label class="form-label">Pièce d'identité du dirigeant / responsable</label>
                                    <input type="file" name="documents[piece_id]" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Autre document (optionnel)</label>
                                    <input type="file" name="documents[autre]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                </div>

                                <button type="submit" class="btn btn-fill-out btn-block mt-30">Envoyer la demande</button>
                                {{-- Ce bouton s'affiche si le client veut passer une commande --}}

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @endif

    </main>
@endsection
@section('jspart')

    <script>
        let notification = document.getElementById('notification');

        setTimeout(() => {
            if (notification) {
                notification.classList.add("off")
            }
        },5000)
    </script>

@endsection
