@extends('layout.main')
@section('title', 'Reçu - ' . ($paiement->numero_recu ?? 'Paiement'))

@section('contenu')
@php
    // Ce reçu est SERVI POUR LES TROIS CAISSES — ventes, demandes de livraison
    // et locations. Le bouton « Retour » pointait en dur sur la caisse des
    // ventes : depuis le reçu d'une location, on atterrissait donc sur
    // /comptant/encaissements, un écran sans rapport.
    //
    // On revient à la page réellement quittée, tenue par
    // MemoriserPagePrecedente. Repli sur la caisse des ventes si la session ne
    // la connaît pas encore.
    $retourUrl = session(\App\Http\Middleware\MemoriserPagePrecedente::CLE_PRECEDENTE)
        ?: route('show.comptant.encaissements');
@endphp

    <div class="content-header d-print-none">
        <h2 class="content-title">Reçu de paiement {{ $paiement->numero_recu ?? '' }}</h2>
        <div>
            <a href="{{ $retourUrl }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour
            </a>
            <button onclick="window.print()" class="btn btn-info">
                <i class="material-icons md-print"></i> Imprimer
            </button>
            <a href="{{ route('show.recuPdf', $paiement->id) }}" class="btn btn-primary">
                <i class="material-icons md-picture_as_pdf"></i> Télécharger PDF
            </a>
            @php
                $courrielClient = $paiement->client?->user?->email ?: ($paiement->client?->email ?: null);
            @endphp
            {{-- 11/09/2026 : le reçu part de lui-même (avance imputée, règlement
                 finalisé) ; ce bouton le (r)envoie tout de suite et dit pourquoi
                 si le courriel ne part pas. --}}
            <form action="{{ route('show.recu.envoyer', $paiement->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary" id="btn-envoyer-recu"
                        title="{{ $courrielClient ? 'Envoyer ce reçu (PDF) à ' . $courrielClient : 'Ce client n\'a pas d\'adresse de courriel' }}{{ $paiement->recu_envoye_le ? ' — dernier envoi le ' . \Help::dateHeure($paiement->recu_envoye_le) : '' }}">
                    <i class="material-icons md-mail"></i> Envoyer par courriel
                </button>
            </form>
        </div>
    </div>
    @if ($paiement->recu_envoye_le || !$courrielClient)
        <p class="text-muted small d-print-none mb-2">
            @if (!$courrielClient)
                Ce client n'a pas d'adresse de courriel : le reçu ne peut pas lui être envoyé.
            @else
                Reçu envoyé par courriel à {{ $courrielClient }} le {{ \Help::dateHeure($paiement->recu_envoye_le) }}.
            @endif
        </p>
    @endif

    @php $isPdf = false; @endphp
    @include('admin.comptant._recu_styles')
    @include('admin.comptant._recu_body')
@endsection
