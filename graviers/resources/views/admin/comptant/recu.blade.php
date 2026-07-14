@extends('layout.main')
@section('title', 'Reçu - ' . ($paiement->numero_recu ?? 'Paiement'))

@section('contenu')
    <div class="content-header d-print-none">
        <h2 class="content-title">Reçu de paiement {{ $paiement->numero_recu ?? '' }}</h2>
        <div>
            <a href="{{ route('show.comptant.encaissements') }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour
            </a>
            <button onclick="window.print()" class="btn btn-info">
                <i class="material-icons md-print"></i> Imprimer
            </button>
            <a href="{{ route('show.comptant.recuPdf', $paiement->id) }}" class="btn btn-primary">
                <i class="material-icons md-picture_as_pdf"></i> Télécharger PDF
            </a>
        </div>
    </div>

    @php $isPdf = false; @endphp
    @include('admin.comptant._recu_styles')
    @include('admin.comptant._recu_body')
@endsection
