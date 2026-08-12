{{-- Bandeau commun aux pages institutionnelles.
     Attend : $chip, $titre, $accroche, et éventuellement $miseAJour. --}}
<section class="page-inst__hero">
    <span class="page-inst__chip">
        <i class="material-icons md-{{ $icone ?? 'info' }}"></i> {{ $chip }}
    </span>
    <h1 class="page-inst__titre">{!! $titre !!}</h1>
    <p class="page-inst__accroche">{!! $accroche !!}</p>
    @if (!empty($miseAJour))
        <p class="page-inst__maj">
            Dernière mise à jour : <strong>{{ \Carbon\Carbon::now()->locale('fr')->isoFormat('D MMMM YYYY') }}</strong>
        </p>
    @endif
</section>
