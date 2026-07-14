{{-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>

    <select class="form-multi-select" id="ms1" multiple data-coreui-search="global">
        <option value="0">Angular</option>
        <option value="1">Bootstrap</option>
        <option value="2">React.js</option>
        <option value="3">Vue.js</option>
        <optgroup label="backend">
            <option value="4">Django</option>
            <option value="5">Laravel</option>
            <option value="6">Node.js</option>
        </optgroup>
    </select>
</body>
</html> --}}

{{-- @dd($total) --}}
@extends('client.main')
@section('title', 'Paiement effectué')
@section('content')
    <style>
        body { background-color: #f8f9fa; }
        .pdf-viewer {
            height: 650px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
    </style>


    <main class="main">

        @include('client.navMobile')
       <div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="text-center mb-4">
                        <h3 class="text-success fw-bold">
                            Paiement effectué avec succès
                        </h3>
                        <p class="text-muted">
                            Votre reçu est disponible ci-dessous
                        </p>
                    </div>

                    <!-- STREAM PDF -->
                    <iframe
                        src="{{ route('paye.facture', ['reference' => $paiement->ligne->id, 'action' => 'voir']) }}"
                        class="w-100 pdf-viewer"
                    ></iframe>

                    <!-- ACTIONS -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('paye.facture', ['reference' => $paiement->ligne->id, 'action' => 'telecharger']) }}?download=1"
                           class="btn btn-primary">
                            Télécharger le reçu
                        </a>

                        <a href="{{route('client.index')}}"
                           class="btn btn-outline-secondary">
                            Revenir au site
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
    </main>
@endsection



